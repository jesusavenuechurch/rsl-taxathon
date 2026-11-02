<?php

namespace App\Models\SourceRecords;

class PayeReturn extends SourceRecord
{
    public const CHANNEL = 'paye';

    protected $table = 'paye_returns';
}
