<?php

namespace App\Livewire\Tour;

use App\Domain\Tour\TourSteps;
use App\Models\User;
use App\Support\Clock;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * "Step N of 9: what to click, what to notice" (officer-desk.md section 8).
 * A narration panel, not an autopilot: Next signs in as the right officer
 * and navigates to the right page; the judge still clicks through the real
 * screens themselves.
 */
class GuidedTour extends Component
{
    public int $step = 0;

    public function mount(): void
    {
        $this->step = (int) session('tour_step', 0);
    }

    public function dismiss(): void
    {
        session()->forget('tour_step');
        $this->step = 0;
    }

    /**
     * The tour's own "time passes" step: moves the demo calendar without
     * handing a judge the administrator's account (demo mode only, and only
     * on a step that offers it).
     */
    public function advanceClock(Clock $clock): mixed
    {
        $days = TourSteps::all()[$this->step]['clockDays'] ?? null;
        abort_unless(config('rsl.demo') && $days, 404);

        $clock->advance($days);
        session(["tour_clock_{$this->step}" => true]);

        return $this->redirect(request()->header('Referer') ?: route('worklist'), navigate: false);
    }

    public function next(): mixed
    {
        return $this->goTo(min($this->step + 1, TourSteps::count()));
    }

    private function goTo(int $step): mixed
    {
        session(['tour_step' => $step]);
        $this->step = $step;
        $target = TourSteps::all()[$step] ?? null;
        if (! $target) {
            return null;
        }

        if ($target['actAs'] ?? null) {
            $officer = User::where('email', $target['actAs'])->first();
            if ($officer) {
                Auth::login($officer);
                session()->regenerate();
                session(['tour_step' => $step]);
            }
        }

        return $this->redirect($target['url'](), navigate: false);
    }

    public function render()
    {
        return view('livewire.tour.guided-tour', [
            'current' => TourSteps::all()[$this->step] ?? null,
            'clockDone' => (bool) session("tour_clock_{$this->step}"),
            'total' => TourSteps::count(),
        ]);
    }
}
