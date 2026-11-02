<?php

namespace App\Models\Analysis;

use App\Models\SourceRecords\Taxpayer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One analysis of one taxpayer, anchored to the filing that prompted it. */
class FilingReview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'findings_count' => 'integer'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class, 'analysis_run_id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(RuleCheck::class)->orderBy('pattern_code');
    }

    public function taxpayer(): BelongsTo
    {
        return $this->belongsTo(Taxpayer::class, 'tin', 'tin')->whereNull('superseded_at');
    }
}
