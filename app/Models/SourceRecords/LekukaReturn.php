<?php

namespace App\Models\SourceRecords;

/** Lekuka e-invoicing monthly summary (not individual invoices). */
class LekukaReturn extends SourceRecord
{
    public const CHANNEL = 'lekuka';

    protected $table = 'lekuka_monthly';
}
