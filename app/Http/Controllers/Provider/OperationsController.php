<?php

namespace App\Http\Controllers\Provider;

use App\Actions\AuditLogger;
use App\Actions\BillingService;
use App\Actions\CloseTicket;
use App\Actions\DashboardTrends;
use App\Actions\PaymentService;
use App\Actions\PlatformSettings;
use App\Actions\ProviderIdentifiers;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\ClientNotice;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\ServiceEvent;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\WaterProvider;
use App\Models\WaterRate;
use App\Notifications\EscalatedTicketNotification;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsController extends Controller
{
    public function index(Request $request, BillingService $billing, DashboardTrends $trends): View
    {
        $request->validate(['search' => ['nullable', 'string', 'max:255'], 'period' => ['nullable', 'date_format:Y-m'], 'sort' => ['nullable', Rule::in(['period_month', 'total', 'balance'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'bill' => ['nullable', Rule::exists('bills', 'id')->where('water_provider_id', $request->user()->water_provider_id)], 'edit_consumer' => ['nullable', 'integer'], 'portal_consumer' => ['nullable', 'integer'], 'delete_consumer' => ['nullable', 'integer'], 'preview_consumer' => ['nullable', 'integer'], 'reject_payment' => ['nullable', 'integer']]);
        $providerId = $request->user()->water_provider_id;
        $module = $request->route()->defaults['module'] ?? 'dashboard';
        $data = ['provider' => $request->user()->waterProvider];
        $search = '%'.$request->input('search', '').'%';
        $consumers = Consumer::where('water_provider_id', $providerId)->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('account_number', 'like', $search)));
        $connections = Connection::with('consumer')->where('water_provider_id', $providerId)->when($request->filled('search'), fn ($q) => $q->whereHas('consumer', fn ($q) => $q->where('name', 'like', $search)->orWhere('account_number', 'like', $search)));
        $bills = Bill::where('water_provider_id', $providerId)->when($request->filled('search'), fn ($q) => $q->whereHas('consumer', fn ($q) => $q->where('name', 'like', $search)->orWhere('account_number', 'like', $search)));

        if ($module === 'dashboard') {
            $validBills = Bill::where('water_provider_id', $providerId)->where('status', '!=', 'void');
            $paid = PaymentAllocation::whereHas('bill', fn ($q) => $q->where('water_provider_id', $providerId)->where('status', '!=', 'void'))->sum('amount');
            $unpaidBills = (clone $validBills)->whereRaw('total > (select coalesce(sum(amount), 0) from payment_allocations where bill_id = bills.id)');
            $data += [
                'financialTrend' => $request->user()->can('billing.view') ? $trends->financial(
                    Bill::where('water_provider_id', $providerId),
                    $request->user()->can('payments.view') ? Payment::where('water_provider_id', $providerId) : null,
                ) : [],
                'consumptionTrend' => $trends->consumption(MeterReading::where('water_provider_id', $providerId)),
                'consumerCount' => $consumers->count(),
                'activeConnections' => (clone $connections)->where('status', 'active')->count(),
                'disconnectedConnections' => (clone $connections)->where('status', 'disconnected')->count(),
                'monthlyBills' => (clone $validBills)->whereDate('period_month', now()->startOfMonth()->toDateString())->count(),
                'unpaidCount' => (clone $unpaidBills)->count(),
                'outstanding' => bcsub((string) $validBills->sum('total'), (string) $paid, 2),
                'monthlyConsumption' => MeterReading::where('water_provider_id', $providerId)->whereDate('period_month', now()->startOfMonth()->toDateString())->selectRaw('COALESCE(SUM(current_reading - previous_reading), 0) as usage_total')->first()->usage_total,
                'collected' => Payment::where('water_provider_id', $providerId)->where('status', 'verified')->sum('amount'),
                'pendingPayments' => Payment::where('water_provider_id', $providerId)->where('status', 'pending')->count(),
                'overdueBills' => (clone $unpaidBills)->whereDate('due_at', '<', today())->count(),
                'openTickets' => SupportTicket::where('water_provider_id', $providerId)->whereNotIn('status', ['resolved', 'closed'])->count(),
                'currentRate' => WaterRate::where('water_provider_id', $providerId)->whereDate('effective_from', '<=', today())->latest('effective_from')->first(),
                'bills' => $request->user()->can('billing.view') ? $bills->with(['consumer', 'allocations'])->latest('id')->limit(5)->get() : collect(),
                'payments' => $request->user()->can('payments.view') ? Payment::where('water_provider_id', $providerId)->latest('id')->limit(5)->get() : collect(),
            ];
        } elseif ($module === 'consumers') {
            $selectedId = $request->input('edit_consumer') ?? $request->input('portal_consumer') ?? $request->input('delete_consumer');
            $selected = $selectedId ? Consumer::with(['portalUser', 'connections'])->where('water_provider_id', $providerId)->findOrFail($selectedId) : null;
            $previewedConsumer = $request->filled('preview_consumer')
                ? Consumer::with(['portalUser', 'connections'])->where('water_provider_id', $providerId)->findOrFail($request->input('preview_consumer'))
                : null;
            $canViewReadings = $request->user()->can('connections.view');
            $canViewBilling = $request->user()->can('billing.view');
            $canViewPayments = $request->user()->can('payments.view');
            $previewReadings = $previewedConsumer && $canViewReadings
                ? MeterReading::with('connection')->where('water_provider_id', $providerId)->whereHas('connection', fn ($query) => $query->where('consumer_id', $previewedConsumer->id))->when($request->filled('period'), fn ($query) => $query->whereDate('period_month', $request->input('period').'-01'))->latest('period_month')->latest('id')->get()
                : collect();
            $previewBills = $previewedConsumer && $canViewBilling
                ? Bill::with(['connection', 'allocations'])->where('water_provider_id', $providerId)->where('consumer_id', $previewedConsumer->id)->where('status', '!=', 'void')->when($request->filled('period'), fn ($query) => $query->whereDate('period_month', $request->input('period').'-01'))->latest('period_month')->latest('id')->get()
                : collect();
            $previewPayments = $previewedConsumer && $canViewPayments
                ? Payment::with(['bill.connection', 'items.bill.connection', 'allocations.bill.connection'])->where('water_provider_id', $providerId)->where('consumer_id', $previewedConsumer->id)->whereHasCoveredBill(fn ($query) => $query->where('status', '!=', 'void')->when($request->filled('period'), fn ($query) => $query->whereDate('period_month', $request->input('period').'-01')))->latest('paid_at')->latest('id')->get()
                : collect();
            $monthlyReports = [];
            $addReport = function (string $period) use (&$monthlyReports): string {
                $month = CarbonImmutable::parse($period)->startOfMonth();
                $key = $month->format('Y-m');
                $monthlyReports[$key] ??= ['period' => $month, 'meters' => [], 'usage' => '0.000', 'bills' => collect(), 'total' => '0.00', 'paid' => '0.00', 'balance' => '0.00', 'pending_count' => 0, 'rejected_count' => 0, 'has_payment_activity' => false];

                return $key;
            };
            foreach ($previewReadings as $reading) {
                $key = $addReport($reading->period_month->toDateString());
                $report = &$monthlyReports[$key];
                $report['meters'][$reading->connection->id] = $reading->connection->meter_number;
                $report['usage'] = bcadd($report['usage'], bcsub($reading->current_reading, $reading->previous_reading, 3), 3);
                unset($report);
            }
            foreach ($previewBills as $bill) {
                $key = $addReport($bill->period_month->toDateString());
                $report = &$monthlyReports[$key];
                $report['meters'][$bill->connection->id] = $bill->connection->meter_number;
                $report['bills']->push($bill);
                $report['total'] = bcadd($report['total'], $bill->total, 2);
                $report['paid'] = bcadd($report['paid'], bcsub($bill->total, $bill->balance(), 2), 2);
                $report['balance'] = bcadd($report['balance'], $bill->balance(), 2);
                unset($report);
            }
            foreach ($previewPayments as $payment) {
                $paymentBills = $payment->coveredBills()->filter(fn (Bill $bill) => $bill->status !== 'void' && (!$request->filled('period') || $bill->period_month->format('Y-m') === $request->input('period')));
                foreach ($paymentBills->groupBy(fn (Bill $bill) => $bill->period_month->toDateString()) as $period => $monthBills) {
                    $key = $addReport($period);
                    $report = &$monthlyReports[$key];
                    if ($canViewReadings) {
                        foreach ($monthBills as $bill) {
                            $report['meters'][$bill->connection->id] = $bill->connection->meter_number;
                        }
                    }
                    $report['has_payment_activity'] = true;
                    if ($payment->status === 'pending') {
                        $report['pending_count']++;
                    } elseif ($payment->status === 'rejected') {
                        $report['rejected_count']++;
                    }
                    unset($report);
                }
            }
            $monthlyReports = collect($monthlyReports)->map(function (array $report): array {
                $meters = array_values($report['meters']);
                sort($meters);
                $statuses = $report['bills']->map(fn (Bill $bill) => $bill->paymentStatus())->countBy();
                $report['meters'] = $meters;
                $report['status'] = $statuses->isEmpty() ? ($report['has_payment_activity'] ? 'Payment activity' : 'Bill pending') : $statuses->map(fn (int $count, string $status) => $count.' '.$status)->implode(' · ');
                $report['status_color'] = $statuses->count() !== 1 ? 'zinc' : match ($statuses->keys()->first()) {
                    'Paid' => 'green', 'Overdue' => 'red', default => 'yellow',
                };

                return $report;
            })->sortByDesc('period')->values();
            $consumerOutstanding = $previewedConsumer && $canViewBilling
                ? Bill::with('allocations')->where('water_provider_id', $providerId)->where('consumer_id', $previewedConsumer->id)->where('status', '!=', 'void')->get()->reduce(fn (string $total, Bill $bill) => bcadd($total, $bill->balance(), 2), '0.00')
                : null;
            $latestReading = $previewedConsumer && $request->user()->can('connections.view')
                ? MeterReading::where('water_provider_id', $providerId)->whereHas('connection', fn ($query) => $query->where('consumer_id', $previewedConsumer->id))->latest('period_month')->latest('id')->first()
                : null;
            $latestBill = $previewedConsumer && $request->user()->can('billing.view')
                ? Bill::where('water_provider_id', $providerId)->where('consumer_id', $previewedConsumer->id)->latest('period_month')->latest('id')->first()
                : null;
            $latestPayment = $previewedConsumer && $request->user()->can('payments.view')
                ? Payment::where('water_provider_id', $providerId)->where('consumer_id', $previewedConsumer->id)->latest('paid_at')->latest('id')->first()
                : null;
            $latestConsumerActivity = collect([
                $latestReading ? ['type' => 'Reading', 'date' => $latestReading->period_month] : null,
                $latestBill ? ['type' => 'Bill', 'date' => $latestBill->period_month] : null,
                $latestPayment ? ['type' => 'Payment', 'date' => $latestPayment->paid_at] : null,
            ])->filter()->sortByDesc('date')->first();
            $data += [
                'consumers' => $previewedConsumer ? null : $consumers->with(['portalUser', 'connections'])->orderBy('name')->paginate(20)->withQueryString(),
                'selectedConsumer' => $selected,
                'previewedConsumer' => $previewedConsumer,
                'monthlyReports' => $monthlyReports,
                'consumerOutstanding' => $consumerOutstanding,
                'latestConsumerActivity' => $latestConsumerActivity,
            ];
        } elseif ($module === 'readings') {
            $providerReadings = MeterReading::where('water_provider_id', $providerId);
            $providerConnections = Connection::where('water_provider_id', $providerId);
            $history = MeterReading::with(['connection.consumer', 'connection.latestReading'])->where('water_provider_id', $providerId)
                ->when($request->filled('search'), fn ($q) => $q->whereHas('connection.consumer', fn ($q) => $q->where('name', 'like', $search)->orWhere('account_number', 'like', $search)))
                ->when($request->filled('period'), fn ($q) => $q->whereDate('period_month', $request->input('period').'-01'))
                ->select('meter_readings.*')
                ->selectSub(Bill::select('id')->whereColumn('meter_reading_id', 'meter_readings.id')->limit(1), 'bill_id');
            $data += [
                'recordedReadingCount' => (clone $providerReadings)->count(),
                'activeConnectionCount' => (clone $providerConnections)->where('status', 'active')->count(),
                'inactiveConnectionCount' => (clone $providerConnections)->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', 'active'))->count(),
                'consumptionTrend' => $trends->consumption($providerReadings),
                'consumers' => $consumers->orderBy('name')->limit(50)->get(),
                'connections' => $connections->when($request->filled('period'), fn ($q) => $q->whereHas('readings', fn ($q) => $q->whereDate('period_month', $request->input('period').'-01')))->orderBy('number')->paginate(20, ['*'], 'connections_page')->withQueryString(),
                'connectionChoices' => Connection::with('consumer')->where('water_provider_id', $providerId)->when($request->filled('search'), fn ($q) => $q->whereHas('consumer', fn ($q) => $q->where('name', 'like', $search)->orWhere('account_number', 'like', $search)))->orderBy('number')->limit(50)->get(),
                'history' => $history->latest('period_month')->latest('id')->paginate(20, ['*'], 'readings_page')->withQueryString(),
            ];
        } elseif ($module === 'billing') {
            $sort = $request->input('sort', 'period_month');
            $direction = $request->input('direction', 'desc');
            $rates = WaterRate::where('water_provider_id', $providerId)->latest('effective_from')->limit(8)->get();
            $latestMonth = CarbonImmutable::parse($rates->first()?->effective_from ?? now())->startOfMonth();
            $rateHistory = WaterRate::where('water_provider_id', $providerId)
                ->whereDate('effective_from', '>=', $latestMonth->subMonths(5)->toDateString())
                ->latest('effective_from')->latest('id')->get()
                ->unique(fn (WaterRate $rate) => $rate->effective_from->format('Y-m'))
                ->keyBy(fn (WaterRate $rate) => $rate->effective_from->format('Y-m'));
            $firstMonth = CarbonImmutable::parse($rateHistory->last()?->effective_from ?? $latestMonth)->startOfMonth();
            $monthCount = max(3, (int) $firstMonth->diffInMonths($latestMonth) + 1);
            $data += [
                'connections' => $connections->orderBy('number')->limit(50)->get(),
                'defaultDueDays' => (int) app(PlatformSettings::class)->numberForProvider($request->user()->waterProvider, 'due_days'),
                'rates' => $rates,
                'rateHistory' => $rateHistory,
                'rateHistoryMonths' => collect(range(0, $monthCount - 1))->map(fn (int $offset) => $firstMonth->addMonths($offset)),
                'sort' => $sort, 'direction' => $direction,
                'bills' => $bills->with(['consumer', 'allocations'])->when($sort === 'balance', fn ($query) => $query->withSum('allocations', 'amount')->orderByRaw('total - COALESCE(allocations_sum_amount, 0) '.$direction), fn ($query) => $query->orderBy($sort, $direction))->latest('period_month')->orderBy('consumer_id')->latest('id')->paginate(20)->withQueryString(),
            ];
        } elseif ($module === 'payments') {
            $selectedBill = $request->filled('bill') ? Bill::with(['consumer', 'connection', 'allocations'])->where('water_provider_id', $providerId)->findOrFail($request->input('bill')) : null;
            $payments = Payment::with(['consumer', 'bill.connection', 'items.bill.connection', 'allocations.bill.connection'])->where('water_provider_id', $providerId)
                ->when($selectedBill, fn ($q) => $q->whereHasCoveredBill(fn ($bill) => $bill->whereKey($selectedBill->id)))
                ->when($request->filled('period'), fn ($q) => $q->where('paid_at', '>=', $request->input('period').'-01')->where('paid_at', '<', CarbonImmutable::parse($request->input('period').'-01')->addMonth()))
                ->when($request->filled('search'), fn ($q) => $q->whereHas('consumer', fn ($q) => $q->where('name', 'like', $search)->orWhere('account_number', 'like', $search)));
            $data += [
                'payments' => $payments->latest('id')->paginate(20)->withQueryString(),
                'bills' => (clone $bills)->with(['consumer', 'allocations', 'adjustments'])->latest('id')->paginate(20, ['*'], 'bills_page')->withQueryString(),
                'billChoices' => (clone $bills)->with('consumer')->withSum('allocations', 'amount')->where('status', '!=', 'void')->whereRaw('total > (select coalesce(sum(amount), 0) from payment_allocations where bill_id = bills.id)')->latest('id')->limit(50)->get(),
                'selectedBill' => $selectedBill,
                'selectedPayment' => $request->filled('reject_payment') ? Payment::where('water_provider_id', $providerId)->findOrFail($request->input('reject_payment')) : null,
                'pendingPaymentCount' => Payment::where('water_provider_id', $providerId)->where('status', 'pending')->count(),
            ];
        }

        return view('provider.'.$module, $data);
    }

    public function consumer(Request $request, AuditLogger $audit, ProviderIdentifiers $identifiers): RedirectResponse
    {
        $data = $request->validate([
            'account_number' => ['nullable', 'string', 'max:80', Rule::unique('consumers')->where('water_provider_id', $request->user()->water_provider_id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'regex:/^\\d+$/', 'max:50'],
            'address' => ['required', 'string', 'max:2000'],
            'number' => ['nullable', 'string', 'max:80', Rule::unique('connections')->where('water_provider_id', $request->user()->water_provider_id)],
            'meter_number' => ['nullable', 'string', 'max:80', Rule::unique('connections')->where('water_provider_id', $request->user()->water_provider_id)],
            'installed_at' => ['nullable', 'date'],
        ]);
        $providerId = $request->user()->water_provider_id;
        DB::transaction(function () use ($data, $providerId, $audit, $request, $identifiers): void {
            $data = $identifiers->assign($providerId, $data + ['account_number' => null, 'number' => null, 'meter_number' => null]);
            $consumerData = collect($data)->only(['account_number', 'name', 'email', 'phone', 'address'])->all();
            $consumer = Consumer::create([...$consumerData, 'water_provider_id' => $providerId]);
            $connection = Connection::create(['water_provider_id' => $providerId, 'consumer_id' => $consumer->id, 'number' => $data['number'], 'meter_number' => $data['meter_number'], 'installed_at' => $data['installed_at'] ?? null]);
            $audit->record($consumer, 'consumer.created', $request->user(), after: $consumerData);
            $audit->record($connection, 'connection.created', $request->user(), after: $connection->only(['number', 'meter_number', 'installed_at']));
        });

        return back()->with('status', 'Consumer and connection created.');
    }

    public function deleteConsumer(Request $request, Consumer $consumer, AuditLogger $audit): RedirectResponse
    {
        $this->scope($request, $consumer);
        DB::transaction(function () use ($request, $consumer, $audit): void {
            WaterProvider::whereKey($consumer->water_provider_id)->lockForUpdate()->firstOrFail();
            $consumer = Consumer::whereKey($consumer->id)->lockForUpdate()->firstOrFail();
            $request->validate(['account_number' => ['required', 'string', Rule::in([$consumer->account_number])]]);
            $before = $consumer->toArray();
            $connectionIds = $consumer->connections()->lockForUpdate()->pluck('id');
            $billIds = Bill::where('consumer_id', $consumer->id)->lockForUpdate()->pluck('id');
            $paymentIds = Payment::where('consumer_id', $consumer->id)->lockForUpdate()->pluck('id');
            $portalUsers = $consumer->portalUser()->lockForUpdate()->get();

            DB::table('sessions')->whereIn('user_id', $portalUsers->pluck('id'))->delete();
            DB::table('password_reset_tokens')->whereIn('email', $portalUsers->pluck('email'))->delete();
            $consumer->portalUser()->delete();
            PaymentAllocation::whereIn('payment_id', $paymentIds)->orWhereIn('bill_id', $billIds)->delete();
            Payment::whereIn('id', $paymentIds)->delete();
            BillAdjustment::whereIn('bill_id', $billIds)->delete();
            Bill::whereIn('id', $billIds)->delete();
            MeterReading::whereIn('connection_id', $connectionIds)->delete();
            ServiceEvent::whereIn('connection_id', $connectionIds)->delete();
            $consumer->connections()->delete();
            SupportTicket::where('consumer_id', $consumer->id)->delete();
            ClientNotice::where('consumer_id', $consumer->id)->delete();
            $consumer->delete();
            $audit->record($consumer, 'consumer.deleted', $request->user(), before: $before);
        });

        return to_route('provider.consumers.index')->with('status', 'Consumer account permanently deleted.');
    }

    public function consumerStatus(Request $request, Consumer $consumer, AuditLogger $audit): RedirectResponse
    {
        $this->scope($request, $consumer);
        $consumer->is_active = ! $consumer->is_active;
        $consumer->save();
        $audit->record($consumer, 'consumer.status_changed', $request->user(), after: ['is_active' => $consumer->is_active]);

        return back()->with('status', 'Consumer status updated.');
    }

    public function activatePortal(Request $request, Consumer $consumer, AuditLogger $audit): RedirectResponse
    {
        $this->scope($request, $consumer);
        abort_if($consumer->portalUser()->exists(), 422);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
        ]);
        DB::transaction(function () use ($consumer, $data, $audit, $request): void {
            $user = User::create(['name' => $consumer->name, 'email' => $data['email'], 'password' => $data['password']]);
            $user->role = UserRole::Consumer;
            $user->consumer_id = $consumer->id;
            $user->email_verified_at = now();
            $user->must_change_password = true;
            $user->save();
            $audit->record($user, 'consumer.portal_activated', $request->user(), after: ['consumer_id' => $consumer->id]);
        });

        return back()->with('status', 'Portal account created. Share the temporary password privately.');
    }

    public function resetConsumerPassword(Request $request, Consumer $consumer, AuditLogger $audit): RedirectResponse
    {
        $this->scope($request, $consumer);
        $portalUser = $consumer->portalUser;
        abort_unless($portalUser, 404);

        if (Password::sendResetLink(['email' => $portalUser->email]) !== Password::RESET_LINK_SENT) {
            return back()->withErrors(['email' => 'The reset link could not be sent. Check mail configuration.']);
        }

        $audit->record($portalUser, 'consumer.password_reset_requested', $request->user(), after: ['consumer_id' => $consumer->id]);

        return back()->with('status', 'Password reset link sent.');
    }

    public function connection(Request $request, AuditLogger $audit): RedirectResponse
    {
        $providerId = $request->user()->water_provider_id;
        $data = $request->validate([
            'consumer_id' => ['required', Rule::exists('consumers', 'id')->where('water_provider_id', $providerId)],
            'number' => ['required', 'string', 'max:80', Rule::unique('connections')->where('water_provider_id', $providerId)],
            'meter_number' => ['required', 'string', 'max:80', Rule::unique('connections')->where('water_provider_id', $providerId)],
            'installed_at' => ['nullable', 'date'],
        ]);
        $connection = Connection::create([...$data, 'water_provider_id' => $providerId]);
        $audit->record($connection, 'connection.created', $request->user(), after: $data);

        return back()->with('status', 'Connection created.');
    }

    public function reading(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'connection_id' => ['required', Rule::exists('connections', 'id')->where('water_provider_id', $request->user()->water_provider_id)],
            'period_month' => ['required', 'date_format:Y-m'],
            'current_reading' => ['required', 'decimal:0,3', 'min:0'],
        ]);
        DB::transaction(function () use ($data, $request, $audit): void {
            Connection::whereKey($data['connection_id'])->lockForUpdate()->firstOrFail();
            $month = CarbonImmutable::parse($data['period_month'].'-01')->toDateString();
            if (MeterReading::where('connection_id', $data['connection_id'])->whereDate('period_month', $month)->exists()) {
                throw ValidationException::withMessages(['period_month' => 'A reading already exists for this month.']);
            }
            if (MeterReading::where('connection_id', $data['connection_id'])->whereDate('period_month', '>', $month)->exists()) {
                throw ValidationException::withMessages(['period_month' => 'Record readings in chronological order.']);
            }
            $lastReading = MeterReading::where('connection_id', $data['connection_id'])->whereDate('period_month', '<', $month)->latest('period_month')->first();
            $previous = $lastReading?->current_reading ?? '0.000';

            $current = (string) $data['current_reading'];
            if (! is_numeric($current)) {
                throw ValidationException::withMessages(['current_reading' => 'Enter a valid meter reading.']);
            }

            if (bccomp($current, $previous, 3) < 0) {
                throw ValidationException::withMessages(['current_reading' => 'The reading cannot be lower than the previous reading.']);
            }

            $reading = MeterReading::create([
                'connection_id' => $data['connection_id'], 'water_provider_id' => $request->user()->water_provider_id,
                'period_month' => $month, 'previous_reading' => $previous,
                'current_reading' => $data['current_reading'], 'recorded_by' => $request->user()->id,
            ]);
            $audit->record($reading, 'reading.recorded', $request->user(), after: ['current_reading' => $reading->current_reading]);

        });

        return back()->with('status', 'Reading recorded.');
    }

    public function rate(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'base_charge' => ['required', 'numeric', 'min:0'],
            'unit_rate' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date', Rule::unique('water_rates')->where('water_provider_id', $request->user()->water_provider_id)],
        ]);
        $rate = WaterRate::create([...$data, 'water_provider_id' => $request->user()->water_provider_id]);
        $audit->record($rate, 'rate.created', $request->user(), after: $data);

        return back()->with('status', 'Rate saved.');
    }

    public function bill(Request $request, BillingService $billing): RedirectResponse
    {
        $data = $request->validate([
            'connection_id' => ['nullable', Rule::exists('connections', 'id')->where('water_provider_id', $request->user()->water_provider_id)],
            'period_month' => ['required', 'date_format:Y-m'],
            'due_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.($request->input('period_month').'-01')],
        ]);
        $month = $data['period_month'].'-01';
        if (! empty($data['connection_id'])) {
            $connection = Connection::where('water_provider_id', $request->user()->water_provider_id)->findOrFail($data['connection_id']);
            $billing->generate($connection, $month, $data['due_at'], $request->user());
            return back()->with('status', 'Bill generated.');
        }

        $generated = 0;
        $skipped = 0;
        Connection::where('water_provider_id', $request->user()->water_provider_id)->where('status', 'active')->chunkById(100, function ($connections) use ($month, $billing, $request, &$generated, &$skipped): void {
            foreach ($connections as $connection) {
                if (Bill::where('connection_id', $connection->id)->whereDate('period_month', $month)->exists() || ! MeterReading::where('connection_id', $connection->id)->whereDate('period_month', $month)->exists()) {
                    $skipped++;
                    continue;
                }
                try {
                    $billing->generate($connection, $month, $request->input('due_at'), $request->user());
                    $generated++;
                } catch (ValidationException) {
                    $skipped++;
                }
            }
        });

        return back()->with('status', "{$generated} bills generated; {$skipped} connections skipped.");
    }

    public function payment(Request $request, AuditLogger $audit): RedirectResponse
    {
        $providerId = $request->user()->water_provider_id;
        $data = $request->validate([
            'bill_id' => ['required', Rule::exists('bills', 'id')->where('water_provider_id', $providerId)],
            'reference' => ['required', 'string', 'max:80', Rule::unique('payments')->where('water_provider_id', $providerId)],
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'e_wallet', 'other'])],
            'paid_at' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $bill = Bill::whereKey($data['bill_id'])->firstOrFail();
        $amount = (string) $data['amount'];
        if (! is_numeric($amount) || $bill->status === 'void' || bccomp($amount, $bill->balance(), 2) > 0) {
            throw ValidationException::withMessages(['amount' => 'The amount exceeds the bill balance.']);
        }
        unset($data['proof']);
        $payment = Payment::create([
            ...$data, 'water_provider_id' => $providerId, 'consumer_id' => $bill->consumer_id,
            'proof_path' => $request->file('proof')?->store('payment-proofs'),
        ]);
        $audit->record($payment, 'payment.recorded', $request->user(), after: ['amount' => $payment->amount, 'bill_id' => $bill->id]);

        return back()->with('status', 'Payment recorded and awaiting verification.');
    }

    public function verifyPayment(Request $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        $this->scope($request, $payment);
        $service->verify($payment, $request->user());

        return back()->with('status', 'Payment verified.');
    }

    public function rejectPayment(Request $request, Payment $payment, AuditLogger $audit): RedirectResponse
    {
        $this->scope($request, $payment);
        $data = $request->validate(['remarks' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($payment, $data, $request, $audit): void {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless($payment->status === 'pending', 422);
            $payment->status = 'rejected';
            $payment->checked_at = now();
            $payment->remarks = $data['remarks'];
            $payment->save();
            $audit->record($payment, 'payment.rejected', $request->user(), after: ['remarks' => $data['remarks']]);
        });

        return back()->with('status', 'Payment rejected.');
    }

    public function escalate(Request $request, SupportTicket $ticket, AuditLogger $audit, PlatformSettings $settings): RedirectResponse
    {
        $this->scope($request, $ticket);
        DB::transaction(function () use ($request, $ticket, $audit): void {
            $ticket = SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $this->scope($request, $ticket);
            abort_if($ticket->status === 'closed' || $ticket->escalated_at !== null, 422);
            $ticket->escalated_at = now();
            $ticket->status = 'under_review';
            $ticket->save();
            $audit->record($ticket, 'ticket.escalated', $request->user());
        });

        if ($settings->get('email_notifications') === '1') {
            User::where('role', UserRole::SuperAdmin->value)->where('is_active', true)
                ->whereNotNull('email')->each(fn (User $admin) => $admin->notify(new EscalatedTicketNotification($ticket->id, $ticket->subject, $ticket->waterProvider->name)));
        }

        return redirect()->route('provider.tickets.index', ['ticket' => $ticket->id])->with('status', 'Ticket escalated to Super Admin.');
    }

    public function replyTicket(Request $request, SupportTicket $ticket, AuditLogger $audit): RedirectResponse
    {
        $this->scope($request, $ticket);
        abort_if($ticket->status === 'closed', 422);
        $data = $request->validateWithBag('reply-to-ticket-'.$ticket->id, [
            'body' => ['required', 'string', 'max:10000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        DB::transaction(function () use ($request, $ticket, $data, $audit): void {
            $ticket = SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $this->scope($request, $ticket);
            abort_if($ticket->status === 'closed', 422);
            $message = $ticket->messages()->create([
                'author_id' => $request->user()->id,
                'body' => $data['body'],
                'attachment_path' => $request->file('attachment')?->store('ticket-attachments'),
                'attachment_name' => $request->file('attachment')?->getClientOriginalName(),
            ]);
            $audit->record($message, 'ticket.message_added', $request->user(), after: ['ticket_id' => $ticket->id]);
        });

        return back()->with('status', 'Response sent.');
    }

    public function closeTicket(Request $request, SupportTicket $ticket, CloseTicket $closeTicket): RedirectResponse
    {
        $this->scope($request, $ticket);
        $closeTicket->handle($ticket, $request->user(), $request->input('resolution'));

        return redirect()->route('provider.tickets.index', ['ticket' => $ticket->id])->with('status', 'Ticket closed.');
    }

    public function attachment(Request $request, TicketMessage $message): StreamedResponse
    {
        $this->scope($request, $message->supportTicket);
        abort_if($message->internal || $message->attachment_path === null, 404);

        return Storage::disk('local')->download($message->attachment_path, $message->attachment_name);
    }

    /** @param Consumer|Payment|SupportTicket $record */
    private function scope(Request $request, $record): void
    {
        abort_unless($record->water_provider_id === $request->user()->water_provider_id, 404);
    }
}
