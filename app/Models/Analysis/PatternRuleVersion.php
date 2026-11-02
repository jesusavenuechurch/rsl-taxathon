<?php

namespace App\Models\Analysis;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One saved configuration of a rule. Versions are never edited: a rule owner
 * changing a threshold or wording saves a new version.
 */
class PatternRuleVersion extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Rule versions are never edited; save a new version.'));
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'evidence_level' => 'integer',
            'parameters' => 'array',
            'variants' => 'array',
            'lawful_explanations' => 'array',
            'must_establish' => 'array',
            'documents_to_request' => 'array',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PatternRule::class, 'pattern_rule_id');
    }

    /** @return array{title: string, kind: string, template: string} */
    public function variant(string $name): array
    {
        return $this->variants[$name] ?? throw new LogicException("Rule {$this->rule->code} v{$this->version} has no variant [{$name}].");
    }
}
