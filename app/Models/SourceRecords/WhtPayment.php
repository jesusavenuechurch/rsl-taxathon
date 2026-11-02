<?php

namespace App\Models\SourceRecords;

/** One payment on a withholding tax schedule. Payees carry no TIN or country; match them to GroupEntity by normalized name. */
class WhtPayment extends SourceRecord
{
    public const CHANNEL = 'wht';

    protected $table = 'wht_payments';

    public static function derivedAttributes(array $attributes): array
    {
        return ['payee_normalized_name' => self::normalizeName($attributes['payee_legal_name'])];
    }
}
