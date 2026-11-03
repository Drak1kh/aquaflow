<?php

namespace App\Http\Middleware;

use App\Models\WaterProvider;
use App\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BindSingleProvider
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(WaterProvider::count() > 1, 409, 'This MVP supports one provider. Reconcile existing provider records before continuing.');
        $provider = WaterProvider::first() ?? WaterProvider::create(['code' => 'aquaflow', 'name' => 'AquaFlow']);
        abort_unless($provider->is_active, 403);
        $user = $request->user();
        if ($user->role === UserRole::Admin) {
            $user->water_provider_id = $provider->id;
            $user->setRelation('waterProvider', $provider);
        } elseif ($user->role === UserRole::Staff) {
            abort_unless($user->water_provider_id === $provider->id, 403);
        } else {
            abort_unless($user->consumer?->water_provider_id === $provider->id, 403);
        }

        return $next($request);
    }
}
