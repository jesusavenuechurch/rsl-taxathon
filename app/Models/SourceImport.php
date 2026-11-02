<?php

namespace App\Models;

use App\Domain\Records\Channel;
use App\Domain\Records\Channels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One file received from an RSL channel. */
class SourceImport extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'warnings' => 'array'];
    }

    public function channelDefinition(): Channel
    {
        return Channels::get($this->channel);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
