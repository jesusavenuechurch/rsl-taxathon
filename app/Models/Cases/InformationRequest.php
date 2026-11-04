<?php

namespace App\Models\Cases;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InformationRequest extends Model
{
    public const DRAFT = 'draft';

    public const SENT = 'sent';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ReviewCase::class, 'case_id');
    }

    public function composer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'composed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InformationRequestItem::class, 'request_id');
    }
}
