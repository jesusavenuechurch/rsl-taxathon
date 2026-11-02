<?php

namespace App\Models\Analysis;

use Illuminate\Database\Eloquent\Model;

class FindingChecklistItem extends Model
{
    public const LAWFUL_EXPLANATION = 'lawful_explanation';

    public const MUST_ESTABLISH = 'must_establish';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }
}
