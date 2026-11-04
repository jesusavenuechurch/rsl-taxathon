<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Roles;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo mode only: lets a super admin see the app as another officer without
 * their password. The real session always stays the admin's — re-checked on
 * every request, so nothing survives a tampered or stale session. Officer-desk
 * design section 1: "a demo convenience, never a production feature".
 */
class ActAsOfficer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('rsl.demo') && session()->has('acting_for') && Auth::check() && Auth::user()->hasRole(Roles::SUPER_ADMIN)) {
            $officer = User::find(session('acting_for'));

            if ($officer) {
                Auth::setUser($officer);
            } else {
                session()->forget(['acting_for', 'acting_by']);
            }
        }

        return $next($request);
    }
}
