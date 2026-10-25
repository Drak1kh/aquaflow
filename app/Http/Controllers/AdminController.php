<?php

namespace App\Http\Controllers;

use App\AuditRecorder;
use App\CsvCell;
use App\DecimalAmount;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\BillingPeriod;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\ProviderMembership;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WaterProvider;
use App\PlatformMetrics;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function index(Request $request, PlatformMetrics $metrics): View
    {
        $this->authorizeAdmin($request);

        return view('pages.admin-dashboard', [
            'providerCount' => WaterProvider::count(),
            'activeProviderCount' => WaterProvider::where('status', 'active')->count(),
            'userCount' => User::count(),
            'activeUserCount' => User::where('status', 'active')->count(),
            'consumerCount' => Consumer::count(),
            'billCount' => Bill::where('status', '!=', 'void')->count(),
            'paymentCount' => Payment::where('status', 'verified')->count(),
            'billedCents' => $metrics->billedCents(),
            'collectedCents' => $metrics->collectedCents(),
            'outstandingCents' => $metrics->outstandingCents(),
            'roleCount' => Role::count(),
            'escalatedTicketCount' => SupportTicket::whereNotNull('escalated_at')->whereNotIn('status', ['resolved', 'closed'])->count(),
            'latestProviders' => WaterProvider::withCount('consumers')->latest()->limit(5)->get(),
            'recentAudits' => AuditLog::latest()->limit(5)->get(),
        ]);
    }

    public function storeUser(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
        ]);

        $user = DB::transaction(function () use ($data, $audit): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::random(64),
                'status' => 'active',
                'role_id' => $data['role_id'] ?? null,
            ]);
            $audit->record($user, 'user.created');

            return $user;
        });

        Password::sendResetLink(['email' => $user->email]);
        $user->sendEmailVerificationNotification();

        return redirect()->route('admin.users.index')->with('status', 'User created. An invitation was sent to '.$user->email.'.');
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
            'reconnection_fee' => SystemSetting::setting('default_reconnection_fee', '0.00'),
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
        if ($request->has('email')) {
            $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'role_id' => ['sometimes', 'nullable', 'integer', Rule::exists('roles', 'id'), Rule::prohibitedIf($user->is_super_admin)],
        ]);
        abort_if($user->id === $request->user()->id && $data['status'] !== 'active', 422, 'You cannot deactivate your own account.');
        $before = $user->only(['name', 'email', 'status', 'role_id']);
        $emailChanged = isset($data['email']) && $data['email'] !== $user->email;
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->update($data);
        $audit->record($user, 'user.updated', $before, $user->only(['name', 'email', 'status', 'role_id']));
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', 'User account updated.');
    }

    public function saveMembership(Request $request, User $user, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'water_provider_id' => ['required', 'integer', Rule::exists('water_providers', 'id')],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        abort_unless(
            WaterProvider::whereKey($data['water_provider_id'])->where('status', 'active')->exists()
                || ProviderMembership::where('water_provider_id', $data['water_provider_id'])->where('user_id', $user->id)->exists(),
            422,
            'New access can only be assigned to active providers.',
        );
        $membership = ProviderMembership::firstOrNew([
            'water_provider_id' => $data['water_provider_id'],
            'user_id' => $user->id,
        ]);
        $before = $membership->exists ? $membership->only(['role_id', 'status']) : null;
        $membership->fill(['role_id' => $data['role_id'], 'status' => $data['status']]);
        $membership->save();
        $audit->record($membership, 'membership.saved', $before);

        return back()->with('status', 'Provider access saved.');
    }

    public function storeRole(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'alpha_dash', 'max:100', 'unique:roles,slug'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);
        $role = DB::transaction(function () use ($data, $audit): Role {
            $role = Role::create(['name' => $data['name'], 'slug' => $data['slug']]);
            $role->permissions()->sync($data['permissions'] ?? []);
            $audit->record($role, 'role.created', null, ['name' => $role->name, 'slug' => $role->slug, 'permissions' => $role->permissions()->pluck('code')->all()]);

            return $role;
        });

        return redirect()->route('admin.roles.index')->with('status', $role->name.' role created.');
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
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);
        DB::transaction(function () use ($role, $data, $audit): void {
            $before = $role->permissions()->pluck('code')->all();
            $oldName = $role->name;
            if (isset($data['name'])) {
                $role->update(['name' => $data['name']]);
            }
            $role->permissions()->sync($data['permissions'] ?? []);
            $audit->record($role, 'role.permissions.updated', ['name' => $oldName, 'permissions' => $before], ['name' => $role->name, 'permissions' => $role->permissions()->pluck('code')->all()]);
        });

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
            'billing_period_id' => ['nullable', 'integer', 'exists:billing_periods,id'],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'e_wallet', 'other'])],
            'payment_status' => ['nullable', Rule::in(['pending', 'verified', 'rejected', 'reversed'])],
        ]);
        $billsQuery = Bill::query()->with(['provider', 'consumer'])
            ->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->where('water_provider_id', $id))
            ->when($filters['billing_period_id'] ?? null, fn ($query, $id) => $query->where('billing_period_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
        $paymentsQuery = Payment::query()->with(['consumer', 'verifier'])
            ->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->where('water_provider_id', $id))
            ->when($filters['payment_method'] ?? null, fn ($query, $method) => $query->where('method', $method))
            ->when($filters['payment_status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));

        return view('pages.admin-overview', [
            'providers' => WaterProvider::withCount(['consumers', 'bills'])
                ->withSum(['bills as billed_total' => fn ($query) => $query->where('status', '!=', 'void')], 'total_amount')
                ->withSum(['payments as collected_total' => fn ($query) => $query->where('status', 'verified')], 'amount')
                ->orderBy('name')->get(),
            'filters' => $filters,
            'billingPeriods' => BillingPeriod::query()
                ->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->where('water_provider_id', $id))
                ->with('provider')->orderByDesc('starts_on')->get(),
            'billCount' => (clone $billsQuery)->where('status', '!=', 'void')->count(),
            'billedCents' => DecimalAmount::units((string) (clone $billsQuery)->where('status', '!=', 'void')->sum('total_amount'), 2),
            'collectedCents' => DecimalAmount::units((string) (clone $paymentsQuery)->where('status', 'verified')->sum('amount'), 2),
            'bills' => $billsQuery->latest()->paginate(20, ['*'], 'bills_page')->withQueryString(),
            'payments' => $paymentsQuery->latest()->paginate(20, ['*'], 'payments_page')->withQueryString(),
            'adjustments' => BillAdjustment::query()->with('bill.consumer')
                ->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->where('water_provider_id', $id))
                ->when($filters['billing_period_id'] ?? null, fn ($query, $id) => $query->whereHas('bill', fn ($bill) => $bill->where('billing_period_id', $id)))
                ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
                ->latest()->limit(10)->get(),
            'billingIssues' => SupportTicket::query()->with('consumer')->whereNotNull('escalated_at')->where('type', 'billing')
                ->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->where('water_provider_id', $id))
                ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
                ->latest()->limit(10)->get(),
            'paymentIssues' => SupportTicket::query()->with('consumer')->whereNotNull('escalated_at')->where('type', 'payment')
                ->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->where('water_provider_id', $id))
                ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
                ->latest()->limit(10)->get(),
            'voidBills' => Bill::query()->with('consumer')->where('status', 'void')
                ->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->where('water_provider_id', $id))
                ->when($filters['billing_period_id'] ?? null, fn ($query, $id) => $query->where('billing_period_id', $id))
                ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
                ->latest()->limit(10)->get(),
            'paymentExceptions' => Payment::query()->with('consumer')->whereIn('status', ['rejected', 'reversed'])
                ->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->where('water_provider_id', $id))
                ->when($filters['payment_method'] ?? null, fn ($query, $method) => $query->where('method', $method))
                ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
                ->latest()->limit(10)->get(),
            'audits' => AuditLog::query()
                ->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->where('water_provider_id', $id))
                ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
                ->latest()->limit(30)->get(),
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
            'defaultReconnectionFee' => SystemSetting::setting('default_reconnection_fee', '0.00'),
            'notificationBillingDefault' => SystemSetting::setting('notification_billing_default', '1'),
            'notificationPaymentDefault' => SystemSetting::setting('notification_payment_default', '1'),
            'notificationSupportDefault' => SystemSetting::setting('notification_support_default', '1'),
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
            'default_reconnection_fee' => ['required', 'numeric', 'min:0', 'max:9999999.99', 'decimal:0,2'],
            'notification_billing_default' => ['required', 'boolean'],
            'notification_payment_default' => ['required', 'boolean'],
            'notification_support_default' => ['required', 'boolean'],
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
            'billing_period_id' => ['nullable', 'integer', 'exists:billing_periods,id'],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'e_wallet', 'other'])],
            'payment_status' => ['nullable', Rule::in(['pending', 'verified', 'rejected', 'reversed'])],
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
                    ->when($filters['billing_period_id'] ?? null, fn ($query, $id) => $query->where('billing_period_id', $id))
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
                    ->when($filters['payment_method'] ?? null, fn ($query, $method) => $query->where('method', $method))
                    ->when($filters['payment_status'] ?? null, fn ($query, $status) => $query->where('status', $status))
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
