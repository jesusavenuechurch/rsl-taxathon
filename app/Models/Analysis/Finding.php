<?php

namespace App\Models\Analysis;

use App\Models\Cases\ReviewCase;
use App\Models\SourceRecords\Taxpayer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A pattern worth an officer's attention, never a conclusion. Shows the
 * observation, lawful explanations, what the officer must establish, an
 * estimated exposure and the exact source rows (rule 4 of the handoff).
 */
class Finding extends Model
{
    public const OPEN = 'open';

    public const SUPERSEDED = 'superseded';   // the same pattern was re-raised with different facts

    public const WITHDRAWN = 'withdrawn';     // a later analysis no longer finds it

    public const CONCLUDED = 'concluded';     // an officer recorded one of the four outcomes

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'evidence_level' => 'integer',
            'exposure_estimate' => 'integer',
            'raised_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @param Builder<static> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::OPEN);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(FindingEvidence::class)->orderBy('position');
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(FindingChecklistItem::class)->orderBy('type')->orderBy('position');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(PatternRuleVersion::class, 'pattern_rule_version_id');
    }

    public function firstReview(): BelongsTo
    {
        return $this->belongsTo(FilingReview::class, 'first_review_id');
    }

    public function taxpayer(): BelongsTo
    {
        return $this->belongsTo(Taxpayer::class, 'tin', 'tin')->whereNull('superseded_at');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ReviewCase::class, 'case_id');
    }

    public function outcome(): HasOne
    {
        return $this->hasOne(OfficerOutcome::class)->latestOfMany();
    }

    /** @return list<string> */
    public function evidenceRecordIds(): array
    {
        return $this->evidence->pluck('record_id')->all();
    }
}
