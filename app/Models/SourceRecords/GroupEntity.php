<?php

namespace App\Models\SourceRecords;

/** An entity in a multinational group, from the company registry / taxpayer disclosure. */
class GroupEntity extends SourceRecord
{
    public const CHANNEL = 'group_register';

    protected $table = 'group_entities';

    public static function derivedAttributes(array $attributes): array
    {
        return ['normalized_name' => self::normalizeName($attributes['entity_name'])];
    }
}
