<?php

namespace App\Models\SourceRecords;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** A written VAT refund application (record_id is the claim ID, RF-). */
class RefundClaim extends SourceRecord
{
    public const CHANNEL = 'refund_claims';

    protected $table = 'refund_claims';

    public function periods(): HasMany
    {
        return $this->hasMany(RefundClaimPeriod::class);
    }

    public function afterImport(): void
    {
        foreach (self::parsePeriods($this->vat_periods) as $period) {
            $this->periods()->create(['period' => $period]);
        }
    }

    /** "2025-04, 2025-05, 2025-06" -> ['2025-04', '2025-05', '2025-06'] */
    public static function parsePeriods(string $periods): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $periods)))));
    }
}
