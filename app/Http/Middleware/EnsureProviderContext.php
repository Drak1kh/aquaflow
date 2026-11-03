<?php

namespace App\Http\Middleware;

use App\Models\WaterProvider;
use App\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProviderContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user->role === UserRole::SuperAdmin) {
            if ($request->routeIs('admin.*', 'dashboard.admin', 'dashboard', 'bills.print', 'payments.print')) {
                return $next($request);
            }
            $request->validate(['provider_context' => ['nullable', 'integer', 'exists:water_providers,id']]);
            if ($request->filled('provider_context')) {
                $request->session()->put('provider_context', $request->integer('provider_context'));
            }
            $providerId = $request->session()->get('provider_context');
            if ($providerId) {
                $provider = WaterProvider::findOrFail($providerId);
            } else {
                abort_if(WaterProvider::count() > 1, 409, 'Select a provider from Provider Management before opening operations.');
                $provider = WaterProvider::first() ?? WaterProvider::create(['code' => 'aquaflow', 'name' => 'AquaFlow']);
            }
            abort_unless($provider->is_active, 403);
            $user->water_provider_id = $provider->id;
            $user->setRelation('waterProvider', $provider);
        } elseif ($user->role === UserRole::WaterProvider) {
            abort_unless($user->waterProvider?->is_active, 403);
        } elseif (! $request->routeIs('admin.*')) {
            abort_unless($user->consumer?->waterProvider?->is_active, 403);
        }

        return $next($request);
    }
}
