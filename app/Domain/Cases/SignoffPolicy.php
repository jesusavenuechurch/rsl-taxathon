<?php

namespace App\Domain\Cases;

use App\Models\Analysis\OfficerOutcome;
use App\Support\Format;
use App\Support\Settings;

/**
 * When closing a finding as "explained, legitimate" needs a second officer's
 * sign-off (handoff rule 10). The threshold is a setting. RSL's default is
 * M1,000,000; no story in the mock data reaches it, so a demo lowers it, and
 * every screen that relies on it says so (never a silent change).
 */
class SignoffPolicy
{
    public const KEY = 'outcomes.second_signoff_threshold';

    public const RSL_DEFAULT = 1_000_000;

    public function __construct(private Settings $settings) {}

    public function threshold(): int
    {
        return (int) $this->settings->get(self::KEY, self::RSL_DEFAULT);
    }

    public function requiresSignoff(string $outcome, int $exposure): bool
    {
        return $outcome === OfficerOutcome::EXPLAINED && $exposure >= $this->threshold();
    }

    /** Plain-language note shown wherever sign-off applies, while the threshold differs from RSL's default. */
    public function demoNote(): ?string
    {
        $threshold = $this->threshold();
        if ($threshold >= self::RSL_DEFAULT) {
            return null;
        }

        $default = Format::money(self::RSL_DEFAULT);

        return $threshold <= 0
            ? "For this demonstration, every \"explained, legitimate\" closure needs a second sign-off. RSL's default is {$default} or more of estimated exposure; it is a setting."
            : 'For this demonstration, the sign-off threshold is lowered to '.Format::money($threshold)." of estimated exposure. RSL's default is {$default}; it is a setting.";
    }
}
