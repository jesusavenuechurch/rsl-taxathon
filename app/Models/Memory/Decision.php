<?php

namespace App\Models\Memory;

use App\Models\SourceRecords\Taxpayer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * An officer's earlier conclusion about a company: organisational memory.
 * Memory informs, never exempts (rule 8): it is shown as context and its
 * conditions are checked, but it never stops a finding being raised.
 */
class Decision extends Model
{
    public const CONCERN_SUPPORTED = 'concern_supported_referred';

    public const EXPLAINED = 'explained_legitimate';

    public const MORE_INFORMATION = 'more_information_needed';

    public const SYSTEM_ERROR = 'system_error'; // never stored here (rule 9)

    public const LABELS = [
        self::CONCERN_SUPPORTED => 'Concern supported, referred',
        self::EXPLAINED => 'Explained, legitimate',
        self::MORE_INFORMATION => 'More information needed',
        self::SYSTEM_ERROR => 'System error',
    ];

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (Decision $d) {
            if ($d->outcome === self::SYSTEM_ERROR) {
                throw new LogicException('System errors go to data review, never into company memory.');
            }
        });
        static::updating(function (Decision $d) {
            if (array_diff(array_keys($d->getDirty()), ['superseded_at', 'updated_at'])) {
                throw new LogicException("Decision {$d->reference} is part of the record and cannot be edited.");
            }
        });
    }

    protected function casts(): array
    {
        return ['decided_on' => 'date', 'superseded_at' => 'datetime'];
    }

    /** @param Builder<static> $query */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('superseded_at');
    }

    public function conditionList(): HasMany
    {
        return $this->hasMany(DecisionCondition::class)->orderBy('position');
    }

    public function taxpayer(): BelongsTo
    {
        return $this->belongsTo(Taxpayer::class, 'tin', 'tin')->whereNull('superseded_at');
    }

    public function outcomeLabel(): string
    {
        return self::LABELS[$this->outcome] ?? $this->outcome;
    }
}
