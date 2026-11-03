<?php

namespace App\Models\Memory;

use Illuminate\Database\Eloquent\Model;

class DecisionCondition extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'float', 'value_max' => 'float', 'tolerance' => 'float'];
    }

    public function isCheckable(): bool
    {
        return $this->metric !== null && $this->operator !== null;
    }
}
