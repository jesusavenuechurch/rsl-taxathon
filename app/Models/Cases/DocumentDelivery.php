<?php

namespace App\Models\Cases;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Created when a request is sent; a clock move turns a due row into a released document. */
class DocumentDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(InformationRequestItem::class, 'request_item_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(SimulatedTaxpayerDocument::class, 'simulated_document_id');
    }
}
