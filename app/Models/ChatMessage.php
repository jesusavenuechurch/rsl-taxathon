<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One question to "Ask the records" and the cited answer it got. */
class ChatMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['answer' => 'array', 'fact_sheet_ids' => 'array', 'asked_at' => 'datetime'];
    }
}
