<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\WaterProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProviderController extends Controller
{
    public function index(Request $request): View
    {
        $providers = WaterProvider::query()
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->withCount(['users', 'consumers'])->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.providers', compact('providers'));
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'alpha_dash', 'max:50', 'unique:water_providers,code'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);
        $provider = WaterProvider::create($data);
        $audit->record($provider, 'provider.created', $request->user(), after: $data);

        return back()->with('status', 'Provider created.');
    }

    public function update(Request $request, WaterProvider $provider, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('water_providers', 'code')->ignore($provider->id)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'due_days' => ['nullable', 'integer', 'between:0,90'],
            'grace_days' => ['nullable', 'integer', 'between:0,90'],
            'late_fee' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);
        $before = $provider->only(array_keys($data));
        $provider->update($data);
        $audit->record($provider, 'provider.updated', $request->user(), $before, $data);

        return back()->with('status', 'Provider updated.');
    }

    public function toggle(Request $request, WaterProvider $provider, AuditLogger $audit): RedirectResponse
    {
        $before = $provider->is_active;
        $provider->update(['is_active' => ! $before]);
        $audit->record($provider, 'provider.status_changed', $request->user(), ['is_active' => $before], ['is_active' => $provider->is_active]);

        return back()->with('status', 'Provider status updated.');
    }
}
