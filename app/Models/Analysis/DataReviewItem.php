<?php

namespace App\Models\Analysis;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Where a system error goes instead of company memory (handoff rule 9). */
class DataReviewItem extends Model
{
    public const OPEN = 'open';

    public const RESOLVED = 'resolved';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    public function outcome(): BelongsTo
    {
        return $this->belongsTo(OfficerOutcome::class, 'raised_from_outcome_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
