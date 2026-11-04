<?php

namespace App\Models\Cases;

use App\Models\Analysis\Finding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InformationRequestItem extends Model
{
    public const REQUESTED = 'requested';

    public const RECEIVED = 'received';

    public const NOT_SUPPLIED = 'not_supplied';

    protected $guarded = [];

    public function request(): BelongsTo
    {
        return $this->belongsTo(InformationRequest::class, 'request_id');
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(DocumentDelivery::class, 'request_item_id');
    }

    public function releasedDocument(): HasOne
    {
        return $this->hasOne(ReleasedDocument::class, 'request_item_id');
    }
}
