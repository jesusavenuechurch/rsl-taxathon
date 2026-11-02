<?php

namespace App\Models\Analysis;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalysisRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['rule_versions' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(FilingReview::class);
    }
}
