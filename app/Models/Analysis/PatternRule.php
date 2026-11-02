<?php

namespace App\Models\Analysis;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A pattern in the catalogue (P01..P14). Its settings live in versions. */
class PatternRule extends Model
{
    public const TRIGGER_FILING = 'filing';

    public const TRIGGER_DOCUMENT = 'document';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PatternRuleVersion::class)->orderBy('version');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(PatternRuleVersion::class)->latestOfMany('version');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
