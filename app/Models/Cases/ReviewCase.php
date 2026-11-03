<?php

namespace App\Models\Cases;

use App\Models\Analysis\Finding;
use App\Models\Memory\Decision;
use App\Models\SourceRecords\RefundClaim;
use App\Models\SourceRecords\Taxpayer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A company's open findings, routed to a team for an officer to work. ("Case" is a PHP keyword.) */
class ReviewCase extends Model
{
    public const RISK_REVIEW = 'risk_review';

    public const REFUND_REVIEW = 'refund_review';

    public const INFORMATION_GAP = 'information_gap';

    public const DATA_CHECK = 'data_check';

    public const OPEN = 'open';

    public const IN_PROGRESS = 'in_progress';

    public const CLOSED = 'closed';

    public const TYPE_LABELS = [
        self::RISK_REVIEW => 'Risk review',
        self::REFUND_REVIEW => 'Refund review',
        self::INFORMATION_GAP => 'Information gap',
        self::DATA_CHECK => 'Data check',
    ];

    protected $table = 'cases';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'closed_at' => 'datetime', 'exposure_estimate' => 'integer'];
    }

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', '!=', self::CLOSED);
    }

    /** Highest priority first, then largest exposure, then oldest. */
    public function scopeRanked(Builder $query): void
    {
        $query->orderByRaw("case priority when 'high' then 1 when 'medium' then 2 else 3 end")
            ->orderByDesc('exposure_estimate')->orderBy('opened_at');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class, 'case_id')->orderByDesc('exposure_estimate')->orderBy('pattern_code');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CaseEvent::class, 'case_id')->orderBy('id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function taxpayer(): BelongsTo
    {
        return $this->belongsTo(Taxpayer::class, 'tin', 'tin')->whereNull('superseded_at');
    }

    public function refundClaim(): BelongsTo
    {
        return $this->belongsTo(RefundClaim::class, 'refund_claim_record_id', 'record_id')->whereNull('superseded_at');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class, 'tin', 'tin')->whereNull('superseded_at');
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function laneLabel(): ?string
    {
        return ['lower_risk' => 'Lower risk', 'needs_review' => 'Needs review'][$this->refund_lane] ?? null;
    }
}
