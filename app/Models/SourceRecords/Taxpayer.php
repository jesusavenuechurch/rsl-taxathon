<?php

namespace App\Models\SourceRecords;

/** A company as registered in eTax (companies.csv). */
class Taxpayer extends SourceRecord
{
    public const CHANNEL = 'companies';

    protected $table = 'taxpayers';
}
