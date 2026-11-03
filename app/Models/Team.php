<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Team extends Model
{
    public const RISK_ANALYSIS = 'risk_analysis';

    public const REFUND_AUDIT = 'refund_audit';

    public const SPECIAL_SECTORS = 'special_sectors';

    public const DATA = 'data';

    /** The RSL units cases move between. */
    public const DEFINITIONS = [
        self::RISK_ANALYSIS => 'Risk analysis',
        self::REFUND_AUDIT => 'Refund audit',
        self::SPECIAL_SECTORS => 'Special Sectors',
        self::DATA => 'Data team',
    ];

    protected $guarded = [];

    /** @return Collection<string, self> every team, keyed by code, creating any missing */
    public static function ensureDefaults()
    {
        foreach (self::DEFINITIONS as $code => $name) {
            self::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        return self::all()->keyBy('code');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
