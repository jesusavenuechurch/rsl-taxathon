<?php

namespace App\Domain\Records;

use App\Models\SourceRecords\SourceRecord;

/** An RSL data channel: where a kind of record comes from and what its file looks like. */
final class Channel
{
    /**
     * @param  class-string<SourceRecord>  $model
     * @param  list<Field>  $fields  in file order
     */
    public function __construct(
        public readonly string $key,
        public readonly string $prefix,
        public readonly string $label,
        public readonly string $rslSystem,
        public readonly string $file,
        public readonly string $model,
        public readonly array $fields,
        public readonly string $idColumn = 'record_id',
        public readonly ?string $tinAttribute = 'tin',
        public readonly ?string $receivedDateAttribute = null,
    ) {}

    public function field(string $attribute): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->attribute === $attribute) {
                return $field;
            }
        }

        return null;
    }
}
