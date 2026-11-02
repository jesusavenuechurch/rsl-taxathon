<?php

namespace App\Support;

use App\Domain\Audit\AuditLog;
use App\Models\Setting;

/**
 * Runtime configuration stored in the database. Every change is audited,
 * because thresholds decide what officers get to see.
 */
class Settings
{
    /** @var array<string, mixed> */
    private array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = Setting::find($key)?->value;
        }

        return $this->cache[$key] ?? $default;
    }

    /** Set a value; pass $audit = false only when the caller writes its own, more specific audit event. */
    public function set(string $key, mixed $value, bool $audit = true): void
    {
        $old = $this->get($key);

        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => auth()->id()]);
        $this->cache[$key] = $value;

        if ($audit && $old !== $value) {
            app(AuditLog::class)->record('setting_changed', subjectType: 'setting', subjectRef: $key, payload: ['from' => $old, 'to' => $value]);
        }
    }
}
