<?php

namespace App\Livewire\Cases;

use App\Models\Cases\ReviewCase;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** "Where should I look?" Cases ranked by priority and estimated exposure. */
#[Layout('components.layouts.app')]
#[Title('Worklist')]
class Worklist extends Component
{
    #[Url]
    public string $tab = '';

    public function mount(): void
    {
        Gate::authorize('view_worklist');
        if (! in_array($this->tab, ['team', 'mine', 'all'], true)) {
            $this->tab = auth()->user()->team_id ? 'team' : 'all';
        }
    }

    public function render()
    {
        $user = auth()->user();
        $query = ReviewCase::active()->with(['taxpayer', 'team', 'assignee'])->withCount(['findings' => fn ($q) => $q->where('status', 'open')])->ranked();

        match ($this->tab) {
            'team' => $query->where('team_id', $user->team_id),
            'mine' => $query->where('assigned_user_id', $user->id),
            default => null,
        };

        return view('livewire.cases.worklist', [
            'cases' => $query->get(),
            'counts' => [
                'team' => $user->team_id ? ReviewCase::active()->where('team_id', $user->team_id)->count() : null,
                'mine' => ReviewCase::active()->where('assigned_user_id', $user->id)->count(),
                'all' => ReviewCase::active()->count(),
            ],
            'teamName' => $user->team?->name,
        ]);
    }
}
