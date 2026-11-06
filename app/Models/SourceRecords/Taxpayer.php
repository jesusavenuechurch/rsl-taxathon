<?php

namespace App\Models\SourceRecords;

/** A company as registered in eTax (companies.csv). */
class Taxpayer extends SourceRecord
{
    public const CHANNEL = 'companies';

    protected $table = 'taxpayers';

    /** "Thaba Trading" rather than "Thaba Trading (Pty) Ltd", for sentences. */
    public function shortName(): string
    {
        return trim(preg_replace('/\s*\((pty|proprietary)\)\s*(ltd|limited)?\s*$/i', '', $this->name));
    }
}
