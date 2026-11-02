<?php

namespace App\Livewire\Analysis;

use App\Models\Analysis\FilingReview;
use App\Models\Analysis\Finding;
use App\Models\SourceRecords\Taxpayer;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Every taxpayer's latest review and open findings (read-only; worklists come with cases). */
#[Layout('components.layouts.app')]
#[Title('Findings')]
class FindingIndex extends Component
{
    public function mount(): void
    {
        Gate::authorize('view_company_records');
    }

    public function render()
    {
        $findings = Finding::open()->orderByDesc('exposure_estimate')->orderBy('pattern_code')->get()->groupBy('tin');
        $reviews = FilingReview::query()->whereIn('id', FilingReview::selectRaw('max(id)')->groupBy('tin'))->get()->keyBy('tin');

        $companies = Taxpayer::current()->orderBy('name')->get()
            ->map(fn ($t) => ['taxpayer' => $t, 'review' => $reviews->get($t->tin), 'findings' => $findings->get($t->tin, collect())])
            ->sortByDesc(fn ($c) => [$c['findings']->where('kind', '!=', 'green_lane')->isNotEmpty(), $c['findings']->sum('exposure_estimate')])
            ->values();

        return view('livewire.analysis.finding-index', ['companies' => $companies]);
    }
}
