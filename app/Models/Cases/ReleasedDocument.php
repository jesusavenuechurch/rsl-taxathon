<?php

namespace App\Models\Cases;

use App\Models\Analysis\Finding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The only taxpayer documents the UI ever shows. */
class ReleasedDocument extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ReviewCase::class, 'case_id');
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(InformationRequestItem::class, 'request_item_id');
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }
}
