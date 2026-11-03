<?php

namespace App\Domain\Memory;

use App\Domain\Audit\AuditLog;
use App\Domain\Records\AlreadyImported;
use App\Domain\Records\ImportFailed;
use App\Models\Memory\Decision;
use App\Models\SourceImport;
use App\Models\Team;
use App\Support\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Loads earlier officer decisions (officer_decisions.csv) into company memory,
 * with the team's checkable reading of their conditions
 * (decision_conditions.json). Decisions are part of the record: one that
 * already exists is never overwritten.
 */
class DecisionImporter
{
    public const CHANNEL = 'officer_decisions';

    private const OUTCOMES = [
        'concern supported' => Decision::CONCERN_SUPPORTED,
        'explained' => Decision::EXPLAINED,
        'more information' => Decision::MORE_INFORMATION,
        'system error' => Decision::SYSTEM_ERROR,
    ];

    public function __construct(private Clock $clock, private AuditLog $audit) {}

    public function import(string $path, string $conditionsPath, bool $force = false): SourceImport
    {
        if (! is_readable($path)) {
            throw new ImportFailed("Cannot read {$path}.");
        }
        $sha = hash_file('sha256', $path);
        if (! $force && ($previous = SourceImport::where('channel', self::CHANNEL)->where('sha256', $sha)->first())) {
            throw new AlreadyImported($previous);
        }

        $rows = $this->rows($path);
        $conditions = is_readable($conditionsPath) ? json_decode(file_get_contents($conditionsPath), true, flags: JSON_THROW_ON_ERROR) : [];
        $warnings = [];

        $import = DB::transaction(function () use ($rows, $conditions, $path, $sha, &$warnings) {
            $import = SourceImport::create([
                'channel' => self::CHANNEL, 'filename' => basename($path), 'sha256' => $sha,
                'received_at' => $this->clock->now(), 'imported_by' => auth()->id(),
                'rows_total' => count($rows), 'rows_new' => 0, 'rows_revised' => 0, 'rows_unchanged' => 0, 'warnings' => [],
            ]);
            $new = $unchanged = 0;

            foreach ($rows as $line => $row) {
                if (Decision::where('reference', $row['decision_id'])->exists()) {
                    $unchanged++;

                    continue;
                }
                $outcome = $this->outcome($row['outcome'], $line);
                if ($outcome === Decision::SYSTEM_ERROR) {
                    $warnings[] = "{$row['decision_id']} is a system error: sent to data review, not loaded into memory.";

                    continue;
                }

                $decision = Decision::create([
                    'reference' => $row['decision_id'],
                    'tin' => $row['tin'],
                    'tax_year_reviewed' => $row['tax_year_reviewed'],
                    'pattern_code' => preg_match('/^(P\d\d)\b/', $row['pattern'], $m) ? $m[1] : null,
                    'pattern_label' => $row['pattern'],
                    'outcome' => $outcome,
                    'observed_pattern' => $row['observed'],
                    'conclusion' => $row['reason'],
                    'evidence' => $row['evidence'],
                    'conditions' => $row['conditions'],
                    'reassessment_point' => $row['reassess_at'],
                    'officer' => $row['officer'],
                    'team_code' => self::teamFrom($row['officer']),
                    'second_signoff' => $row['second_signoff'] ?: null,
                    'decided_on' => $row['decided_on'],
                    'source' => 'imported',
                    'source_import_id' => $import->id,
                ]);

                $readings = $conditions[$row['decision_id']] ?? [['text' => $row['conditions']]];
                if (! isset($conditions[$row['decision_id']])) {
                    $warnings[] = "{$row['decision_id']}: no checkable reading of its conditions; shown as text only.";
                }
                foreach ($readings as $i => $c) {
                    $decision->conditionList()->create([
                        'position' => $i + 1,
                        'text' => $c['text'],
                        'metric' => $c['metric'] ?? null,
                        'operator' => $c['operator'] ?? null,
                        'value' => $c['value'] ?? null,
                        'value_max' => $c['value_max'] ?? null,
                        'tolerance' => $c['tolerance'] ?? null,
                    ]);
                }
                $new++;
            }

            $import->forceFill(['rows_new' => $new, 'rows_unchanged' => $unchanged, 'warnings' => $warnings])->save();

            return $import;
        });

        $this->audit->record('received', subjectType: 'source_import', subjectRef: (string) $import->id, payload: [
            'channel' => self::CHANNEL, 'file' => $import->filename, 'sha256' => $sha,
            'rows' => $import->rows_total, 'new' => $import->rows_new, 'unchanged' => $import->rows_unchanged,
            'warnings' => count($import->warnings),
        ]);

        return $import;
    }

    /** "Officer P. Mokoena (Special Sectors)" -> special_sectors */
    public static function teamFrom(string $officer): ?string
    {
        if (! preg_match('/\(([^)]+)\)/', $officer, $m)) {
            return null;
        }

        return Team::ensureDefaults()->first(fn ($t) => stripos($m[1], $t->name) !== false)?->code;
    }

    private function outcome(string $text, int $line): string
    {
        foreach (self::OUTCOMES as $needle => $outcome) {
            if (str_contains(strtolower($text), $needle)) {
                return $outcome;
            }
        }

        throw new ImportFailed("Line {$line}: unknown outcome '{$text}'.");
    }

    private function rows(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), fgetcsv($handle, escape: ''));
        $required = ['decision_id', 'tin', 'tax_year_reviewed', 'pattern', 'observed', 'outcome', 'reason', 'evidence', 'conditions', 'reassess_at', 'officer', 'second_signoff', 'decided_on'];
        if ($missing = array_diff($required, $header)) {
            throw new ImportFailed('Missing column(s): '.implode(', ', $missing).'.');
        }

        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            $line++;
            if ($values !== [null]) {
                $rows[$line] = array_map('trim', array_combine($header, $values));
            }
        }
        fclose($handle);

        return $rows;
    }
}
