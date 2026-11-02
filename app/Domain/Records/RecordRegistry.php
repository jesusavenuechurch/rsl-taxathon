<?php

namespace App\Domain\Records;

use App\Models\SourceRecords\SourceRecord;
use Illuminate\Support\Collection;

/**
 * Finds any source row by its public ID (CIT-00036, WHT-00042, RF-0001...).
 * Findings, chat citations and outcomes all point at these IDs.
 */
class RecordRegistry
{
    /** The current version, or a specific one. */
    public function find(string $recordId, ?int $version = null): ?SourceRecord
    {
        $channel = Channels::forRecordId($recordId);
        if (! $channel) {
            return null;
        }

        $query = $channel->model::query()->where('record_id', strtoupper(trim($recordId)));

        return $version === null
            ? $query->current()->first()
            : $query->where('version', $version)->first();
    }

    /** @return Collection<int, SourceRecord> current versions, in the order asked for */
    public function findMany(array $recordIds): Collection
    {
        return collect($recordIds)
            ->groupBy(fn ($id) => Channels::forRecordId($id)?->key)
            ->flatMap(fn ($ids, $key) => $key === '' ? [] : Channels::get($key)->model::query()->current()->whereIn('record_id', $ids)->get())
            ->keyBy('record_id')
            ->pipe(fn ($found) => collect($recordIds)->map(fn ($id) => $found->get($id))->filter()->values());
    }
}
