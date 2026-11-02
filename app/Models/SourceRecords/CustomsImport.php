<?php

namespace App\Models\SourceRecords;

class CustomsImport extends SourceRecord
{
    public const CHANNEL = 'customs';

    protected $table = 'customs_imports';
}
