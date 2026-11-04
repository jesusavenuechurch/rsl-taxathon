<?php

namespace App\Models\Analysis;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FindingChecklistItem extends Model
{
    public const LAWFUL_EXPLANATION = 'lawful_explanation';

    public const MUST_ESTABLISH = 'must_establish';

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
}
