<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Consumer;
use App\Models\User;
use App\Models\WaterProvider;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::with('waterProvider')->when($request->filled('search'), function ($query) use ($request): void {
            $search = '%'.$request->string('search').'%';
            $query->where(fn ($query) => $query->where('name', 'like', $search)->orWhere('email', 'like', $search));
        })->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.users', [
            'users' => $users,
            'providers' => WaterProvider::orderBy('name')->get(),
            'consumers' => Consumer::orderBy('name')->get(),
        ]);
    }

    public function show(User $user): View
    {
        return view('admin.user', [
            'user' => $user->load(['waterProvider', 'consumer']),
            'activity' => AuditLog::where('subject_type', $user->getMorphClass())->where('subject_id', $user->id)->latest('id')->limit(30)->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validatedAccount($request);
        $password = $request->validate(['password' => ['required', 'string', 'min:12', 'confirmed']])['password'];

        DB::transaction(function () use ($request, $data, $password, $audit): void {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $password]);
            $this->assignAccount($user, $data);
            $user->email_verified_at = now();
            $user->must_change_password = true;
            $user->save();
            $audit->record($user, 'user.created', $request->user(), after: ['role' => $user->role->value, 'water_provider_id' => $user->water_provider_id, 'consumer_id' => $user->consumer_id]);
        });

        return back()->with('status', 'Account created. Give the temporary password to the user through a private channel.');
    }

    public function update(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validatedAccount($request, $user);

        DB::transaction(function () use ($request, $user, $data, $audit): void {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->guardLastAdmin($user, $data['role'] !== UserRole::SuperAdmin->value);
            $before = $user->only(['name', 'email', 'role', 'water_provider_id', 'consumer_id']);
            $user->fill(['name' => $data['name'], 'email' => $data['email']]);
            $this->assignAccount($user, $data);
            $user->save();
            $audit->record($user, 'user.updated', $request->user(), $before, $user->only(['name', 'email', 'role', 'water_provider_id', 'consumer_id']));
        });

        return back()->with('status', 'Account updated.');
    }

    public function toggle(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $user, $audit): void {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->guardLastAdmin($user, $user->is_active);
            $before = $user->is_active;
            $user->is_active = ! $before;
            $user->save();
            $audit->record($user, 'user.status_changed', $request->user(), ['is_active' => $before], ['is_active' => $user->is_active]);
        });

        return back()->with('status', 'Account status updated.');
    }

    public function resetPassword(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $result = Password::sendResetLink(['email' => $user->email]);

        if ($result !== Password::RESET_LINK_SENT) {
            return back()->withErrors(['email' => 'The reset link could not be sent. Check mail configuration.']);
        }

        $audit->record($user, 'user.password_reset_requested', $request->user());

        return back()->with('status', 'Password reset link sent.');
    }

    /** @return array{name: string, email: string, role: string, water_provider_id?: int|null, consumer_id?: int|null} */
    private function validatedAccount(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'water_provider_id' => ['nullable', 'integer', 'exists:water_providers,id'],
            'consumer_id' => ['nullable', 'integer', 'exists:consumers,id'],
        ]);

        $data['water_provider_id'] = $request->user()->water_provider_id;

        if ($data['role'] === UserRole::Consumer->value && empty($data['consumer_id'])) {
            throw ValidationException::withMessages(['consumer_id' => 'Choose a consumer record for this account.']);
        }

        if (! empty($data['consumer_id']) && User::where('consumer_id', $data['consumer_id'])->when($user, fn ($query) => $query->where('id', '!=', $user->id))->exists()) {
            throw ValidationException::withMessages(['consumer_id' => 'This consumer already has a portal account.']);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function assignAccount(User $user, array $data): void
    {
        $user->role = UserRole::from($data['role']);
        $user->water_provider_id = $user->role === UserRole::WaterProvider ? $data['water_provider_id'] : null;
        $user->consumer_id = $user->role === UserRole::Consumer ? $data['consumer_id'] : null;
    }

    private function guardLastAdmin(User $user, bool $removingAccess): void
    {
        if ($removingAccess && $user->role === UserRole::SuperAdmin && $user->is_active && User::where('role', UserRole::SuperAdmin->value)->where('is_active', true)->whereKeyNot($user->id)->lockForUpdate()->first(['id']) === null) {
            throw ValidationException::withMessages(['role' => 'The last active Admin must remain active.']);
        }
    }
}
