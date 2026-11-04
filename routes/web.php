<?php

use App\Domain\Audit\AuditLog;
use App\Livewire\Admin\DataIntake;
use App\Livewire\Admin\NotificationsSent;
use App\Livewire\Analysis\FilingReviewShow;
use App\Livewire\Analysis\FindingIndex;
use App\Livewire\Analysis\FindingShow;
use App\Livewire\Auth\DemoFront;
use App\Livewire\Auth\Login;
use App\Livewire\Cases\CaseShow;
use App\Livewire\Cases\Desk;
use App\Livewire\Cases\Results;
use App\Livewire\Home;
use App\Livewire\Records\RecordShow;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Auth::check() ? 'home' : (config('rsl.demo') ? 'demo.front' : 'login')));

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/welcome', DemoFront::class)->name('demo.front');
});

Route::middleware('auth')->group(function () {
    Route::get('/home', Home::class)->name('home');
    Route::get('/worklist', Desk::class)->name('worklist');
    Route::get('/results', Results::class)->name('results');
    Route::get('/cases/{reference}', CaseShow::class)->name('cases.show');
    Route::get('/intake', DataIntake::class)->name('intake');
    Route::get('/notifications', NotificationsSent::class)->name('notifications.sent');
    Route::get('/findings', FindingIndex::class)->name('findings.index');
    Route::get('/findings/{reference}', FindingShow::class)->name('findings.show');
    Route::get('/reviews/{reference}', FilingReviewShow::class)->name('reviews.show');
    Route::get('/records/{recordId}', RecordShow::class)->name('records.show')
        ->where('recordId', '[A-Za-z]+-[0-9]+');

    Route::post('/logout', function (Request $request) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    // Demo mode only: the administrator sees the app as another officer.
    // /act-as/stop must be declared before the {user} wildcard, or "stop" is
    // swallowed as a user-binding segment.
    Route::post('/act-as/stop', function (Request $request) {
        $officer = $request->session()->pull('acting_for');
        $admin = User::find($request->session()->pull('acting_by'));

        // Restores the admin on this request too, not just the next one: the
        // guard otherwise keeps whichever user Auth::setUser() last swapped to.
        if ($admin) {
            Auth::setUser($admin);
        }
        if ($officer) {
            app(AuditLog::class)->record('acting_stopped', subjectType: 'user', subjectRef: (string) $officer);
        }

        return redirect()->route('home');
    })->name('act-as.stop');

    Route::post('/act-as/{user}', function (User $user) {
        abort_unless(config('rsl.demo') && Auth::user()->hasRole(Roles::SUPER_ADMIN), 404);

        session(['acting_for' => $user->id, 'acting_by' => Auth::id()]);
        app(AuditLog::class)->record('acting_started', subjectType: 'user', subjectRef: (string) $user->id,
            payload: ['acting_for' => $user->id]);

        return redirect()->route('home');
    })->name('act-as');
});
