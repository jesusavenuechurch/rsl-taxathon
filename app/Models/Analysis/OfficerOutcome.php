<?php

namespace App\Models\Analysis;

use App\Models\Cases\ReviewCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An officer's conclusion on one finding, in five parts (handoff rule 7). The AI drafts nothing here; the officer writes it. */
class OfficerOutcome extends Model
{
    public const CONCERN_SUPPORTED = 'concern_supported_referred';

    public const EXPLAINED = 'explained_legitimate';

    public const MORE_INFORMATION = 'more_information_needed';

    public const SYSTEM_ERROR = 'system_error';

    public const LABELS = [
        self::CONCERN_SUPPORTED => 'Concern supported, referred',
        self::EXPLAINED => 'Explained, legitimate',
        self::MORE_INFORMATION => 'More information needed',
        self::SYSTEM_ERROR => 'System error',
    ];

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const RETURNED = 'returned';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'signed_off_at' => 'datetime',
            'requires_second_signoff' => 'boolean',
            'exposure_at_decision' => 'integer',
        ];
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ReviewCase::class, 'case_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function secondSigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'second_signer_id');
    }

    public function outcomeLabel(): string
    {
        return self::LABELS[$this->outcome] ?? $this->outcome;
    }

    public function isFinal(): bool
    {
        return ! $this->requires_second_signoff || $this->signoff_status === self::APPROVED;
    }
}
