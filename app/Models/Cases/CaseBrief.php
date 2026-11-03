<?php

namespace App\Models\Cases;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The AI's consolidated brief for a case: every statement cites a record ID
 * from the case's fact sheet. The AI drafts; it never concludes — this row
 * only ever reaches "approved", "edited" or "rejected" through an officer.
 */
class CaseBrief extends Model
{
    public const DRAFT = 'draft';

    public const APPROVED = 'approved';

    public const EDITED = 'edited';

    public const REJECTED = 'rejected';

    public const SOURCE_AI = 'ai';

    public const SOURCE_TEMPLATE = 'template';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fact_sheet_ids' => 'array',
            'draft' => 'array',
            'generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ReviewCase::class, 'case_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function isReviewed(): bool
    {
        return $this->status !== self::DRAFT;
    }

    /** All citations used anywhere in the draft, for linking record IDs in the UI. */
    public function citations(): array
    {
        $statements = $this->draft['statements'] ?? [];
        $next = $this->draft['next_step']['citations'] ?? [];

        return array_values(array_unique([...array_merge(...array_map(fn ($s) => $s['citations'] ?? [], $statements ?: [[]])), ...$next]));
    }
}
