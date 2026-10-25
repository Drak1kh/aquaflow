<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountReady
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->is_active || ($user->water_provider_id && ! $user->waterProvider?->is_active) || ($user->consumer_id && (! $user->consumer?->is_active || ! $user->consumer->waterProvider?->is_active)))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account is inactive. Contact an administrator.']);
        }

        if ($user?->must_change_password && ! $request->routeIs('password.change', 'password.change.store', 'logout')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
