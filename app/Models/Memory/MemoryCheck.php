<?php

namespace App\Models\Memory;

use App\Models\Analysis\Finding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One analysis's check of an earlier decision's conditions against current figures. */
class MemoryCheck extends Model
{
    public const WITHIN = 'within_conditions';

    public const OUTSIDE = 'outside_conditions';

    public const NOT_CHECKABLE = 'not_checkable';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['condition_results' => 'array'];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }
}
