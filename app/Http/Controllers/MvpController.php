<?php

namespace App\Http\Controllers;

use App\Actions\AuditLogger;
use App\Actions\PlatformSettings;
use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\WaterProvider;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MvpController extends Controller
{
    public function management(Request $request, string $section): View
    {
        abort_unless(in_array($section, ['balances', 'reports', 'settings'], true), 404);
        if ($section === 'settings') {
            Gate::authorize('provider.settings.manage');
        }
        if ($section !== 'settings') {
            Gate::authorize($section === 'reports' ? 'reports.view' : 'payments.view');
        }
        $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'search' => ['nullable', 'string', 'max:255'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'overdue' => ['nullable', 'boolean']]);
        $provider = $request->user()->waterProvider;
        if ($section === 'settings') {
            return view('provider.summary', compact('section', 'provider'));
        }
        $balanceSql = 'total - (select coalesce(sum(amount), 0) from payment_allocations where bill_id = bills.id)';
        $billsQuery = Bill::with(['consumer', 'allocations'])->where('water_provider_id', $provider->id)->where('status', '!=', 'void')
            ->when($request->filled('month'), fn ($q) => $q->whereDate('period_month', $request->input('month').'-01'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('period_month', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('period_month', '<=', $request->input('to')))
            ->when($request->boolean('overdue'), fn ($q) => $q->whereDate('due_at', '<', today()))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('consumer', fn ($q) => $q->where('name', 'like', '%'.$request->input('search').'%')->orWhere('account_number', 'like', '%'.$request->input('search').'%')))
            ->when($section === 'balances', fn ($q) => $q->whereRaw('('.$balanceSql.') > 0'));
        $billCount = (clone $billsQuery)->count();
        $billedTotal = (string) (clone $billsQuery)->sum('total');
        $outstanding = (clone $billsQuery)->reorder()->selectRaw('COALESCE(SUM('.$balanceSql.'), 0) as balance_total')->first()->balance_total;
        $bills = $billsQuery->latest('period_month')->paginate(20)->withQueryString();
        $collected = '0.00';
        $consumption = '0.000';
        if ($section === 'reports') {
            $payments = Payment::where('water_provider_id', $provider->id)->where('status', 'verified')
                ->when($request->filled('month'), fn ($q) => $q->where('paid_at', '>=', $request->input('month').'-01')->where('paid_at', '<', CarbonImmutable::parse($request->input('month').'-01')->addMonth()))
                ->when($request->filled('from'), fn ($q) => $q->whereDate('paid_at', '>=', $request->input('from')))
                ->when($request->filled('to'), fn ($q) => $q->whereDate('paid_at', '<=', $request->input('to')))
                ->when($request->filled('search'), fn ($q) => $q->whereHas('consumer', fn ($q) => $q->where('name', 'like', '%'.$request->input('search').'%')->orWhere('account_number', 'like', '%'.$request->input('search').'%')));
            $collected = bcadd((string) $payments->sum('amount'), '0', 2);
            $readings = MeterReading::where('water_provider_id', $provider->id)
                ->when($request->filled('month'), fn ($q) => $q->whereDate('period_month', $request->input('month').'-01'))
                ->when($request->filled('from'), fn ($q) => $q->whereDate('period_month', '>=', $request->input('from')))
                ->when($request->filled('to'), fn ($q) => $q->whereDate('period_month', '<=', $request->input('to')))
                ->when($request->filled('search'), fn ($q) => $q->whereHas('connection.consumer', fn ($q) => $q->where('name', 'like', '%'.$request->input('search').'%')->orWhere('account_number', 'like', '%'.$request->input('search').'%')));
            $consumption = bcadd((string) $readings->selectRaw('COALESCE(SUM(current_reading - previous_reading), 0) as usage_total')->first()->usage_total, '0', 3);
        }
        $graceDays = (int) ($provider->grace_days ?? app(PlatformSettings::class)->get('grace_days'));
        $lateFee = (string) ($provider->late_fee ?? app(PlatformSettings::class)->get('late_fee'));

        return view('provider.summary', compact('section', 'provider', 'bills', 'billCount', 'billedTotal', 'outstanding', 'collected', 'consumption', 'graceDays', 'lateFee'));
    }

    public function billingPeriod(Request $request, AuditLogger $audit): RedirectResponse
    {
        $providerId = $request->user()->water_provider_id;
        $data = $request->validate([
            'period_month' => ['required', 'date_format:Y-m'],
            'due_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.($request->input('period_month').'-01')],
        ]);
        $month = $data['period_month'].'-01';
        $period = DB::transaction(function () use ($providerId, $month, $data): BillingPeriod {
            WaterProvider::whereKey($providerId)->lockForUpdate()->firstOrFail();
            if (BillingPeriod::where('water_provider_id', $providerId)->whereDate('period_month', $month)->exists()) {
                throw ValidationException::withMessages(['period_month' => 'This billing period already exists.']);
            }

            return BillingPeriod::create(['water_provider_id' => $providerId, 'period_month' => $month, 'due_at' => $data['due_at']]);
        });
        $audit->record($period, 'billing_period.created', $request->user(), after: $data);

        return back()->with('status', 'Billing period created.');
    }

    public function providerSettings(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'address' => ['nullable', 'string', 'max:2000'], 'phone' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email'], 'due_days' => ['required', 'integer', 'min:0', 'max:90'], 'grace_days' => ['nullable', 'integer', 'min:0', 'max:90'], 'late_fee' => ['nullable', 'decimal:0,2', 'min:0', 'max:99999999999']]);
        $provider = $request->user()->waterProvider;
        $provider->update($data);
        $audit->record($provider, 'provider.updated', $request->user(), after: $data);

        return back()->with('status', 'Provider settings saved.');
    }

    public function updateConsumer(Request $request, Consumer $consumer, AuditLogger $audit): RedirectResponse
    {
        abort_unless($consumer->water_provider_id === $request->user()->water_provider_id, 404);
        $data = $request->validate(['account_number' => ['sometimes', 'required', 'string', 'max:80', Rule::unique('consumers')->where('water_provider_id', $consumer->water_provider_id)->ignore($consumer->id)], 'name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email'], 'address' => ['required', 'string', 'max:2000']]);
        $consumer->update($data);
        $audit->record($consumer, 'consumer.updated', $request->user(), after: $data);

        return back()->with('status', 'Consumer updated.');
    }

    public function correctReading(Request $request, MeterReading $reading, AuditLogger $audit): RedirectResponse
    {
        abort_unless($reading->water_provider_id === $request->user()->water_provider_id, 404);
        $data = $request->validate(['current_reading' => ['required', 'decimal:0,3', 'gte:0']]);
        DB::transaction(function () use ($reading, $data, $request, $audit): void {
            Connection::whereKey($reading->connection_id)->lockForUpdate()->firstOrFail();
            $reading = MeterReading::whereKey($reading->id)->lockForUpdate()->firstOrFail();
            if (Bill::where('meter_reading_id', $reading->id)->exists() || MeterReading::where('connection_id', $reading->connection_id)->where('period_month', '>', $reading->period_month)->exists()) {
                throw ValidationException::withMessages(['current_reading' => 'Only the latest unbilled reading can be corrected.']);
            }
            if (bccomp($data['current_reading'], $reading->previous_reading, 3) < 0) {
                throw ValidationException::withMessages(['current_reading' => 'The reading cannot be lower than the previous reading.']);
            }
            $reading->update($data);
            $audit->record($reading, 'reading.corrected', $request->user(), after: $data);
        });

        return back()->with('status', 'Reading corrected.');
    }

    public function submitPayment(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('payments.view');
        $consumer = $request->user()->consumer;
        $data = $request->validate([
            'bill_id' => ['required', Rule::exists('bills', 'id')->where('consumer_id', $consumer->id)],
            'reference' => ['required', 'string', 'max:80', Rule::unique('payments')->where('water_provider_id', $consumer->water_provider_id)],
            'amount' => ['required', 'decimal:0,2', 'gt:0'], 'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $bill = Bill::findOrFail($data['bill_id']);
        if ($bill->status === 'void' || bccomp($data['amount'], $bill->balance(), 2) > 0) {
            throw ValidationException::withMessages(['amount' => 'The amount exceeds the bill balance.']);
        }
        unset($data['proof']);
        $payment = Payment::create([...$data, 'consumer_id' => $consumer->id, 'water_provider_id' => $consumer->water_provider_id, 'method' => 'other', 'proof_path' => $request->file('proof')->store('payment-proofs', 'local')]);
        $audit->record($payment, 'payment.submitted', $request->user(), after: ['amount' => $payment->amount]);

        return back()->with('status', 'Payment submitted for verification.');
    }

    public function bill(Request $request, Bill $bill): View
    {
        Gate::authorize('billing.view');
        $this->authorizeDocument($request, $bill->consumer_id, $bill->water_provider_id);

        return view('consumer.document', ['bill' => $bill->load(['consumer', 'waterProvider', 'allocations', 'adjustments']), 'payment' => null]);
    }

    public function receipt(Request $request, Payment $payment): View
    {
        Gate::authorize('payments.view');
        $this->authorizeDocument($request, $payment->consumer_id, $payment->water_provider_id);
        abort_unless($payment->status === 'verified', 404);

        return view('consumer.document', ['bill' => Bill::with(['consumer', 'waterProvider', 'allocations', 'adjustments'])->findOrFail($payment->bill_id), 'payment' => $payment]);
    }

    public function proof(Request $request, Payment $payment): StreamedResponse
    {
        Gate::authorize('payments.view');
        abort_unless($payment->water_provider_id === $request->user()->water_provider_id && $payment->proof_path, 404);

        return Storage::disk('local')->download($payment->proof_path);
    }

    private function authorizeDocument(Request $request, int $consumerId, int $providerId): void
    {
        if ($request->user()->role === UserRole::SuperAdmin) {
            return;
        }
        abort_unless($request->user()->role === UserRole::Consumer ? $request->user()->consumer_id === $consumerId : $request->user()->water_provider_id === $providerId, 404);
    }
}
