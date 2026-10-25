<?php

namespace App;

use App\Models\WaterProvider;
use Illuminate\Http\Request;

class ActiveProvider
{
    public function current(Request $request): WaterProvider
    {
        $user = $request->user();
        abort_unless($user && $user->status === 'active', 403);

        $selectedId = $request->session()->get('active_provider_id');

        if ($selectedId === null && ! $user->is_super_admin) {
            $selectedId = $user->memberships()->where('status', 'active')->whereHas('provider', fn ($query) => $query->where('status', 'active'))->value('water_provider_id');
        }

        abort_unless($selectedId, 403, 'Select a water provider.');
        $provider = WaterProvider::whereKey($selectedId)->where('status', 'active')->firstOrFail();
        abort_unless($user->is_super_admin || $user->memberships()->where('water_provider_id', $provider->id)->where('status', 'active')->exists(), 403);

        return $provider;
    }

    public function authorize(Request $request, string $permission): WaterProvider
    {
        $provider = $this->current($request);
        abort_unless($request->user()->canInProvider($permission, $provider->id), 403);

        return $provider;
    }
}
