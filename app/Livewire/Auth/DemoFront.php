<?php

namespace App\Livewire\Auth;

use App\Domain\Tour\TourSteps;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Demo mode's way in (officer-desk.md section 1): one card per officer, no
 * password. Never reachable unless RSL_DEMO=true (guarded by the route).
 */
#[Layout('components.layouts.app')]
#[Title('RSL Case Review — demo')]
class DemoFront extends Component
{
    public const CARDS = [
        ['l.thamae@rsl.test', 'L. Thamae', 'Risk analyst', 'Where should I look?'],
        ['n.lebona@rsl.test', 'N. Lebona', 'Refund auditor', 'Can I process this refund confidently?'],
        ['p.mokoena@rsl.test', 'P. Mokoena', 'Special Sectors', 'What is happening in this company?'],
        ['m.sello@rsl.test', 'M. Sello', 'Team lead', 'Can I sign this off?'],
        ['k.molapo@rsl.test', 'K. Molapo', 'Data team', 'Is this figure wrong?'],
    ];

    public function enterAs(string $email): mixed
    {
        abort_unless(config('rsl.demo'), 404);

        $officer = User::where('email', $email)->whereIn('email', array_column(self::CARDS, 0))->first();
        abort_unless($officer, 404);

        Auth::login($officer);
        session()->regenerate();

        return $this->redirect(route('home'), navigate: false);
    }

    public function startTour(): mixed
    {
        abort_unless(config('rsl.demo'), 404);

        $first = TourSteps::all()[1];
        $officer = User::where('email', $first['actAs'])->first();
        abort_unless($officer, 404);

        Auth::login($officer);
        session()->regenerate();
        session(['tour_step' => 1]);

        return $this->redirect($first['url'](), navigate: false);
    }

    /** "Fresh database, registry and memory loaded, every company received and analysed" (officer-desk.md section 8). */
    public function resetDemo(): mixed
    {
        abort_unless(config('rsl.demo'), 404);

        $key = 'demo-reset:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $this->addError('reset', 'The demo was just reset. Give it a moment before trying again.');

            return null;
        }
        RateLimiter::hit($key, 30);

        Artisan::call('rsl:demo', ['--fresh' => true, '--all' => true]);

        // migrate:fresh drops and recreates the sessions table mid-request:
        // the CSRF token already embedded in this page's HTML goes stale for
        // any further Livewire call (a full reload is the only fix), and a
        // session flash set here is not a reliable way to carry the
        // confirmation across that same table rebuild. A query parameter is.
        return $this->redirect(route('demo.front', ['reset' => 1]), navigate: false);
    }

    public function render()
    {
        abort_unless(config('rsl.demo'), 404);

        return view('livewire.auth.demo-front', [
            'officers' => User::whereIn('email', array_column(self::CARDS, 0))->get()->keyBy('email'),
        ]);
    }
}
