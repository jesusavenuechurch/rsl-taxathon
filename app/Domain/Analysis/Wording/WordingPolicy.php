<?php

namespace App\Domain\Analysis\Wording;

/**
 * Rules 1 and 2 of the handoff: the system never concludes, and wording may
 * not claim more than the evidence level supports. Applied to every finding,
 * and later to chat answers and notifications.
 */
final class WordingPolicy
{
    /** Conclusions only an officer may reach (and "valid": green lane is "lower risk"). */
    private const FORBIDDEN = '/\b(evasion|evade[sd]?|evading|abuse[sd]?|abusive|fraud\w*|illegal|unlawful|non-?compliant|guilty|wrongdoing|valid)\b/i';

    /** Phrases of the evidence ladder and the lowest level that may use each. */
    private const LADDER = [
        'appear inconsistent with each other' => 2,
        'group structure suggests' => 3,
        'does not appear to match the evidence provided' => 4,
    ];

    /** @return list<string> problems; empty when the wording is acceptable */
    public static function violations(string $text, int $evidenceLevel): array
    {
        $problems = [];

        if (preg_match_all(self::FORBIDDEN, $text, $m)) {
            $problems[] = 'States a conclusion only an officer can reach: "'.implode('", "', array_unique(array_map('strtolower', $m[0]))).'".';
        }
        foreach (self::LADDER as $phrase => $minimum) {
            if ($evidenceLevel < $minimum && stripos($text, $phrase) !== false) {
                $problems[] = "\"{$phrase}\" needs evidence level {$minimum}; this is level {$evidenceLevel}.";
            }
        }

        return $problems;
    }

    public static function assertAcceptable(string $text, int $evidenceLevel): void
    {
        if ($problems = self::violations($text, $evidenceLevel)) {
            throw new WordingRejected($text, $problems);
        }
    }
}
