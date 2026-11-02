<?php

namespace App\Models\Analysis;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleCheck extends Model
{
    public const FIRED = 'fired';

    public const NOT_FIRED = 'not_fired';

    public const NOT_APPLICABLE = 'not_applicable';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metrics' => 'array'];
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(PatternRuleVersion::class, 'pattern_rule_version_id');
    }
}
