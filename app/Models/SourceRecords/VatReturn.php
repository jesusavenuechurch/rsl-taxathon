<?php

namespace App\Models\SourceRecords;

class VatReturn extends SourceRecord
{
    public const CHANNEL = 'vat';

    protected $table = 'vat_returns';
}
