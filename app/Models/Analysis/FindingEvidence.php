<?php

namespace App\Models\Analysis;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FindingEvidence extends Model
{
    public $timestamps = false;

    protected $table = 'finding_evidence';

    protected $guarded = [];

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }
}
