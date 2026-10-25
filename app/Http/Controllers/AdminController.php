<?php

namespace App\Http\Controllers;

use App\AuditRecorder;
use App\CsvCell;
use App\DecimalAmount;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\ProviderMembership;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WaterProvider;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->is_super_admin && $request->user()->status === 'active', 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('pages.admin', [
            'providers' => WaterProvider::withCount('consumers')->orderBy('name')->get(),
            'roles' => Role::with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::orderBy('code')->get(),
            'users' => User::withCount(['memberships', 'consumers'])->orderBy('name')->paginate(20),
            'providerCount' => WaterProvider::count(),
            'consumerCount' => Consumer::count(),
            'billCount' => Bill::where('status', '!=', 'void')->count(),
        ]);
    }

    public function storeProvider(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'alpha_dash', 'max:40', 'unique:water_providers,code'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $provider = WaterProvider::create([
            ...$data,
            'grace_days' => (int) SystemSetting::setting('default_grace_days', '0'),
            'disconnection_days' => (int) SystemSetting::setting('default_disconnection_days', '30'),
            'late_fee' => SystemSetting::setting('default_late_fee', '0.00'),
        ]);
        $audit->record($provider, 'provider.created');

        return back()->with('status', 'Provider created.');
    }

    public function updateProvider(Request $request, WaterProvider $provider, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
        $before = $provider->getAttributes();
        $provider->update($data);
        $audit->record($provider, 'provider.updated', $before);

        return back()->with('status', 'Provider updated.');
    }

    public function updateUser(Request $request, User $user, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'inactive', 'suspended'])]]);
        abort_if($user->id === $request->user()->id && $data['status'] !== 'active', 422);
        $before = ['status' => $user->status];
        $user->update($data);
        $audit->record($user, 'user.status.updated', $before);

        return back()->with('status', 'User status updated.');
    }

    public function storeRole(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'alpha_dash', 'max:100', 'unique:roles,slug'],
        ]);
        $role = Role::create($data);
        $audit->record($role, 'role.created');

        return back()->with('status', 'Role created. Assign permissions below.');
    }

    public function inviteStaff(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'water_provider_id' => ['required', 'exists:water_providers,id'],
            'role_id' => ['required', 'exists:roles,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::firstOrCreate(['email' => $data['email']], [
            'name' => $data['name'],
            'password' => Str::random(64),
        ]);
        $membership = ProviderMembership::updateOrCreate(
            ['water_provider_id' => $data['water_provider_id'], 'user_id' => $user->id],
            ['role_id' => $data['role_id'], 'status' => 'active'],
        );
        $audit->record($membership, 'membership.saved');
        Password::sendResetLink(['email' => $user->email]);
        $user->sendEmailVerificationNotification();

        return back()->with('status', 'Staff invitation sent.');
    }

    public function updateRole(Request $request, Role $role, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['permissions' => ['array'], 'permissions.*' => ['integer', Rule::exists('permissions', 'id')]]);
        $before = $role->permissions()->pluck('code')->all();
        $role->permissions()->sync($data['permissions'] ?? []);
        $audit->record($role, 'role.permissions.updated', ['permissions' => $before], ['permissions' => $role->permissions()->pluck('code')->all()]);

        return back()->with('status', 'Role permissions updated.');
    }

    public function switchProvider(Request $request): RedirectResponse
    {
        $data = $request->validate(['water_provider_id' => ['required', 'integer', Rule::exists('water_providers', 'id')->where('status', 'active')]]);
        $providerId = (int) $data['water_provider_id'];
        abort_unless($request->user()->is_super_admin || $request->user()->memberships()->where('water_provider_id', $providerId)->where('status', 'active')->exists(), 403);
        $request->session()->put('active_provider_id', $providerId);

        return redirect()->route('dashboard');
    }

    public function overview(Request $request): View
    {
        $this->authorizeAdmin($request);
        $filters = $request->validate([
            'water_provider_id' => ['nullable', 'integer', 'exists:water_providers,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $billsQuery = Bill::query()->with(['provider', 'consumer'])
            ->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->where('water_provider_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
        $paymentsQuery = Payment::query()->with('consumer')
            ->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->where('water_provider_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));

        return view('pages.admin-overview', [
            'providers' => WaterProvider::withCount(['consumers', 'bills'])
                ->withSum(['bills as billed_total' => fn ($query) => $query->where('status', '!=', 'void')], 'total_amount')
                ->withSum(['payments as collected_total' => fn ($query) => $query->where('status', 'verified')], 'amount')
                ->orderBy('name')->get(),
            'filters' => $filters,
            'billCount' => (clone $billsQuery)->where('status', '!=', 'void')->count(),
            'billedCents' => DecimalAmount::units((string) (clone $billsQuery)->where('status', '!=', 'void')->sum('total_amount'), 2),
            'collectedCents' => DecimalAmount::units((string) (clone $paymentsQuery)->where('status', 'verified')->sum('amount'), 2),
            'bills' => $billsQuery->latest()->paginate(20, ['*'], 'bills_page')->withQueryString(),
            'payments' => $paymentsQuery->latest()->paginate(20, ['*'], 'payments_page')->withQueryString(),
            'audits' => AuditLog::latest()->limit(30)->get(),
        ]);
    }

    public function settings(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('pages.admin-settings', [
            'platformName' => SystemSetting::setting('platform_name', 'AquaFlow'),
            'supportEmail' => SystemSetting::setting('support_email', ''),
            'defaultGraceDays' => SystemSetting::setting('default_grace_days', '0'),
            'defaultDisconnectionDays' => SystemSetting::setting('default_disconnection_days', '30'),
            'defaultLateFee' => SystemSetting::setting('default_late_fee', '0.00'),
        ]);
    }

    public function updateSettings(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'platform_name' => ['required', 'string', 'max:100'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'default_grace_days' => ['required', 'integer', 'between:0,365'],
            'default_disconnection_days' => ['required', 'integer', 'between:1,365'],
            'default_late_fee' => ['required', 'numeric', 'min:0', 'max:9999999.99', 'decimal:0,2'],
        ]);

        foreach ($data as $key => $value) {
            $setting = SystemSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
            $audit->record($setting, 'system_setting.updated');
        }

        return back()->with('status', 'System settings saved. New provider defaults use these values.');
    }

    public function exportOverview(Request $request): StreamedResponse
    {
        $this->authorizeAdmin($request);
        $filters = $request->validate([
            'type' => ['required', Rule::in(['bills', 'payments'])],
            'water_provider_id' => ['nullable', 'integer', 'exists:water_providers,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $type = $filters['type'];

        return response()->streamDownload(function () use ($filters, $type): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('Unable to open CSV output.');
            }
            if ($type === 'bills') {
                fputcsv($output, ['Bill', 'Provider', 'Consumer', 'Amount', 'Status']);
                Bill::query()->with(['provider', 'consumer'])
                    ->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->where('water_provider_id', $id))
                    ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                    ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
                    ->chunkById(100, function ($bills) use ($output): void {
                        foreach ($bills as $bill) {
                            fputcsv($output, [CsvCell::safe($bill->bill_number), CsvCell::safe($bill->provider->name), CsvCell::safe($bill->consumer->name), $bill->total_amount, $bill->status]);
                        }
                    });
            } else {
                fputcsv($output, ['Reference', 'Provider', 'Consumer', 'Amount', 'Status']);
                Payment::query()->with('consumer.provider')
                    ->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->where('water_provider_id', $id))
                    ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                    ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
                    ->chunkById(100, function ($payments) use ($output): void {
                        foreach ($payments as $payment) {
                            fputcsv($output, [CsvCell::safe($payment->reference_number), CsvCell::safe($payment->consumer->provider->name), CsvCell::safe($payment->consumer->name), $payment->amount, $payment->status]);
                        }
                    });
            }
            fclose($output);
        }, 'aquaflow-'.$type.'-report.csv', ['Content-Type' => 'text/csv']);
    }
}
