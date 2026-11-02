<?php

namespace Database\Seeders;

use App\Domain\Analysis\RuleCatalogue;
use App\Models\Analysis\PatternRule;
use App\Models\Analysis\PatternRuleVersion;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds the 14 patterns as version 1 from pattern_rules.csv (descriptions)
 * and RuleCatalogue (thresholds, wording). Existing rules are left alone:
 * once a rule owner has edited a rule, re-seeding must not undo it.
 */
class RuleCatalogueSeeder extends Seeder
{
    public const FILE = 'database/data/rsl/pattern_rules.csv';

    /** Statutory rates the rules use (handoff: VAT 15%, CIT 25%, WHT 25%, mock treaty 10%). */
    public const RATES = ['tax.vat_rate' => 0.15, 'tax.cit_rate' => 0.25, 'tax.wht_standard_rate' => 0.25, 'tax.wht_treaty_rate_za' => 0.10];

    public function run(): void
    {
        foreach (self::RATES as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        $defaults = RuleCatalogue::defaults();

        foreach ($this->rows() as $row) {
            $code = $row['code'];
            $config = $defaults[$code] ?? throw new RuntimeException("No catalogue entry for {$code}.");

            $rule = PatternRule::firstOrCreate(['code' => $code], [
                'name' => $row['pattern'],
                'plain_language' => $row['rule_in_plain_language'],
                'data_channels' => $row['data_channels'],
                'legal_source' => $row['legal_source'],
                'trigger' => $config['trigger'] ?? PatternRule::TRIGGER_FILING,
            ]);

            if ($rule->versions()->exists()) {
                continue;
            }

            PatternRuleVersion::create([
                'pattern_rule_id' => $rule->id,
                'version' => 1,
                'evidence_level' => (int) $row['evidence_level'],
                'parameters' => $config['parameters'],
                'variants' => $config['variants'],
                'lawful_explanations' => self::list($row['lawful_explanations']),
                'must_establish' => self::questions($row['what_officer_must_establish']),
                'documents_to_request' => str_starts_with($row['documents_to_request'], '(') ? [] : self::list($row['documents_to_request']),
                'exposure_basis' => $config['exposure_basis'],
                'change_reason' => 'Initial catalogue (pattern_rules.csv)',
            ]);
        }
    }

    private function rows(): array
    {
        $handle = fopen(base_path(self::FILE), 'r');
        $header = array_map(fn ($h) => preg_replace('/^\xEF\xBB\xBF/', '', $h), fgetcsv($handle, escape: ''));
        $rows = [];
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            if ($values !== [null]) {
                $rows[] = array_combine($header, array_map('trim', $values));
            }
        }
        fclose($handle);

        return $rows;
    }

    /** "Start-up phase; expansion; market downturn" -> ['Start-up phase', 'Expansion', 'Market downturn'] */
    private static function list(string $text): array
    {
        return array_values(array_map(fn ($s) => ucfirst(trim($s)), array_filter(explode(';', $text), fn ($s) => trim($s) !== '')));
    }

    /** Split "Is X? Is Y?" into separate questions, keeping plain sentences whole. */
    private static function questions(string $text): array
    {
        $parts = preg_split('/(?<=\?)\s+/', trim($text));

        return array_values(array_filter(array_map('trim', $parts)));
    }
}
