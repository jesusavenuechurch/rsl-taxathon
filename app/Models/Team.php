<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    public const RISK_ANALYSIS = 'risk_analysis';

    public const REFUND_AUDIT = 'refund_audit';

    public const SPECIAL_SECTORS = 'special_sectors';

    public const DATA = 'data';

    protected $guarded = [];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
