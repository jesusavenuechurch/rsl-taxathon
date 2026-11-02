<?php

namespace App\Models\SourceRecords;

class CitReturn extends SourceRecord
{
    public const CHANNEL = 'cit';

    protected $table = 'cit_returns';
}
