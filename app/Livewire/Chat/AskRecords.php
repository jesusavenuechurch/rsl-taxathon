<?php

namespace App\Livewire\Chat;

use App\Domain\Chat\ChatAnswer;
use App\Domain\Chat\RecordsChat;
use App\Models\Cases\ReviewCase;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Ask the records": a full page (/ask) and a panel on every case page. Answers
 * only from the records, each statement cited (handoff rule 12). The case is
 * passed as an id, because Livewire fills an optional model parameter with an
 * empty model rather than null, which would make the full page act as a case panel.
 */
#[Layout('components.layouts.app')]
#[Title('Ask the records')]
class AskRecords extends Component
{
    public const SUGGESTED = [
        'Which companies had a significant increase in related-party payments this year?',
        'Why was Highveld Retail Lesotho flagged?',
        'Which companies have recurring VAT credits, and which look lower risk?',
        'Has Qiloane Hardware been reviewed before?',
        'Show me companies paying entities in low-tax jurisdictions.',
    ];

    public ?int $caseId = null;

    public string $question = '';

    /** @var list<array{question: string, statements: array, source: string, provider: ?string, note: ?string}> */
    public array $messages = [];

    public function mount(?int $caseId = null): void
    {
        Gate::authorize('use_chat');
        $this->caseId = $caseId ?: null;
    }

    public function ask(RecordsChat $chat, ?string $suggested = null): void
    {
        Gate::authorize('use_chat');
        $question = trim($suggested ?? $this->question);
        if ($question === '') {
            return;
        }
        $this->validate(['question' => 'max:500'], ['question.max' => 'Keep the question under 500 characters.']);

        $answer = $chat->ask($question, auth()->user(), $this->caseId ? ReviewCase::find($this->caseId) : null);

        array_unshift($this->messages, [
            'question' => $question,
            'statements' => $answer->statements,
            'source' => $answer->source,
            'provider' => $answer->provider,
            'note' => $answer->note,
        ]);
        $this->reset('question');
    }

    public function render()
    {
        return view('livewire.chat.ask-records', ['embedded' => $this->caseId !== null, 'none' => ChatAnswer::NONE]);
    }
}
