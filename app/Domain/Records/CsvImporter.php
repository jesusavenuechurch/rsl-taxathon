<?php

namespace App\Domain\Records;

use App\Domain\Audit\AuditLog;
use App\Events\SourceRecordsImported;
use App\Models\SourceImport;
use App\Models\SourceRecords\SourceRecord;
use App\Models\SourceRecords\Taxpayer;
use App\Support\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Loads one channel's file. All or nothing: any invalid row rejects the whole
 * file. Each row is new (version 1), unchanged (skipped) or revised (a new
 * version; the old one is marked superseded, never edited). Rows absent from
 * a file are left alone: files can be partial, e.g. one month.
 */
class CsvImporter
{
    public function __construct(private Clock $clock, private AuditLog $audit) {}

    public function import(Channel $channel, string $path, bool $force = false): SourceImport
    {
        if (! is_readable($path)) {
            throw new ImportFailed("Cannot read {$path}.");
        }

        $sha = hash_file('sha256', $path);
        if (! $force && ($previous = SourceImport::where('channel', $channel->key)->where('sha256', $sha)->first())) {
            throw new AlreadyImported($previous);
        }

        $rows = $this->parse($channel, $path);

        [$import, $tins] = DB::transaction(fn () => $this->store($channel, basename($path), $sha, $rows));

        $this->audit->record('received', subjectType: 'source_import', subjectRef: (string) $import->id, payload: [
            'channel' => $channel->key,
            'file' => $import->filename,
            'sha256' => $sha,
            'rows' => $import->rows_total,
            'new' => $import->rows_new,
            'revised' => $import->rows_revised,
            'unchanged' => $import->rows_unchanged,
            'warnings' => count($import->warnings),
        ]);

        if ($tins !== []) {
            SourceRecordsImported::dispatch($import, $tins);
        }

        return $import;
    }

    /** @return list<array<string, mixed>> normalized attributes per row */
    private function parse(Channel $channel, string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, escape: '');
        if ($header === false) {
            throw new ImportFailed('The file is empty.');
        }
        $header = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), $header);

        $expected = array_map(fn (Field $f) => $f->column, $channel->fields);
        if ($missing = array_diff($expected, $header)) {
            throw new ImportFailed('Missing column(s): '.implode(', ', $missing).". Is this the {$channel->label} file?");
        }

        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            $line++;
            if ($values === [null] || $values === ['']) {
                continue; // blank line
            }
            if (count($values) !== count($header)) {
                throw new ImportFailed("Line {$line}: expected ".count($header).' values, found '.count($values).'.');
            }
            $rows[] = $this->normalize($channel, array_combine($header, $values), $line);
        }
        fclose($handle);

        if ($rows === []) {
            throw new ImportFailed('The file has a header but no rows.');
        }
        $ids = array_column($rows, 'record_id');
        if ($duplicates = array_unique(array_diff_assoc($ids, array_unique($ids)))) {
            throw new ImportFailed('Record ID(s) appear more than once: '.implode(', ', $duplicates).'.');
        }

        return $rows;
    }

    private function normalize(Channel $channel, array $raw, int $line): array
    {
        $attributes = [];

        foreach ($channel->fields as $field) {
            $value = trim((string) $raw[$field->column]);
            $where = "Line {$line}, {$field->column}";

            if ($value === '') {
                if (! $field->nullable) {
                    throw new ImportFailed("{$where}: a value is required.");
                }
                $attributes[$field->attribute] = null;

                continue;
            }

            $attributes[$field->attribute] = match ($field->type) {
                Field::MONEY, Field::INTEGER => preg_match('/^-?\d+$/', $value) ? (int) $value
                    : throw new ImportFailed("{$where}: '{$value}' is not a whole number."),
                Field::YES_NO => match (strtolower($value)) {
                    'yes' => true, 'no' => false,
                    default => throw new ImportFailed("{$where}: expected Yes or No, found '{$value}'."),
                },
                Field::DATE => $this->validDate($value) ? $value : throw new ImportFailed("{$where}: '{$value}' is not a date (YYYY-MM-DD)."),
                Field::PERIOD => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) ? $value
                    : throw new ImportFailed("{$where}: '{$value}' is not a period (YYYY-MM)."),
                Field::TAX_YEAR => preg_match('/^\d{4}\/\d{2}$/', $value) ? $value
                    : throw new ImportFailed("{$where}: '{$value}' is not a tax year (e.g. 2025/26)."),
                default => $value,
            };
        }

        $attributes['record_id'] = strtoupper($attributes['record_id']);
        if (Channels::forRecordId($attributes['record_id'])?->key !== $channel->key) {
            throw new ImportFailed("Line {$line}: record ID {$attributes['record_id']} does not belong to the {$channel->label} file (expected {$channel->prefix}-).");
        }

        return $attributes;
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /** @return array{0: SourceImport, 1: list<string>} */
    private function store(Channel $channel, string $filename, string $sha, array $rows): array
    {
        /** @var class-string<SourceRecord> $model */
        $model = $channel->model;
        $now = $this->clock->now();

        $import = SourceImport::create([
            'channel' => $channel->key,
            'filename' => $filename,
            'sha256' => $sha,
            'received_at' => $now,
            'imported_by' => auth()->id(),
            'rows_total' => count($rows),
            'rows_new' => 0, 'rows_revised' => 0, 'rows_unchanged' => 0,
            'warnings' => [],
        ]);

        $current = $model::query()->current()->whereIn('record_id', array_column($rows, 'record_id'))
            ->get(['id', 'record_id', 'version', 'row_hash'])->keyBy('record_id');

        $counts = ['new' => 0, 'revised' => 0, 'unchanged' => 0];
        $tins = [];

        foreach ($rows as $attributes) {
            $hash = self::rowHash($attributes);
            $existing = $current->get($attributes['record_id']);

            if ($existing && $existing->row_hash === $hash) {
                $counts['unchanged']++;

                continue;
            }

            if ($existing) {
                $existing->update(['superseded_at' => $now]);
                $counts['revised']++;
            } else {
                $counts['new']++;
            }

            $record = $model::create([
                ...$attributes,
                ...$model::derivedAttributes($attributes),
                'version' => ($existing->version ?? 0) + 1,
                'source_import_id' => $import->id,
                'row_hash' => $hash,
            ]);
            $record->afterImport();

            if ($tin = $record->tin()) {
                $tins[$tin] = true;
            }
        }

        // Counts and warnings are only known now; same transaction.
        $import->forceFill([
            'rows_new' => $counts['new'],
            'rows_revised' => $counts['revised'],
            'rows_unchanged' => $counts['unchanged'],
            'warnings' => $this->warnings($channel, $rows),
        ])->save();

        return [$import, array_keys($tins)];
    }

    /** Problems worth telling the importer about that do not block the file. */
    private function warnings(Channel $channel, array $rows): array
    {
        $warnings = [];
        $sample = fn (array $ids) => implode(', ', array_slice($ids, 0, 5)).(count($ids) > 5 ? ' and '.(count($ids) - 5).' more' : '');

        if ($channel->tinAttribute && $channel->key !== 'companies') {
            $known = Taxpayer::query()->current()->pluck('tin')->flip();
            $unknown = collect($rows)->reject(fn ($r) => isset($known[$r[$channel->tinAttribute]]))->pluck('record_id')->all();
            if ($unknown) {
                $warnings[] = count($unknown).' row(s) for a TIN not registered in eTax: '.$sample($unknown).'.';
            }
        }

        if ($attribute = $channel->receivedDateAttribute) {
            $today = $this->clock->today()->toDateString();
            $future = collect($rows)->filter(fn ($r) => $r[$attribute] > $today)->pluck('record_id')->all();
            if ($future) {
                $warnings[] = count($future)." row(s) with {$attribute} after today ({$today}): ".$sample($future).'.';
            }
        }

        return $warnings;
    }

    /** Fingerprint of a row's contents, to tell unchanged rows from corrections. */
    public static function rowHash(array $attributes): string
    {
        ksort($attributes);

        return hash('sha256', json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
