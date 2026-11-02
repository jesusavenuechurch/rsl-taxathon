<?php

use App\Livewire\Analysis\FilingReviewShow;
use App\Livewire\Analysis\FindingIndex;
use App\Livewire\Analysis\FindingShow;
use App\Livewire\Auth\Login;
use App\Livewire\Home;
use App\Livewire\Records\RecordShow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Auth::check() ? 'home' : 'login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/home', Home::class)->name('home');
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
});
