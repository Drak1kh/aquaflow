<?php

namespace App\Http\Controllers;

use App\ActiveProvider;
use App\AuditRecorder;
use App\BillingCalculator;
use App\DecimalAmount;
use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\BillingPeriod;
use App\Models\MeterReading;
use App\Models\WaterRate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BillingController extends Controller
{
    public function index(Request $request, ActiveProvider $active): View
    {
        $provider = $active->authorize($request, 'bills.view');

        return view('pages.billing', [
            'provider' => $provider,
            'rates' => WaterRate::where('water_provider_id', $provider->id)->latest('effective_from')->get(),
            'periods' => BillingPeriod::where('water_provider_id', $provider->id)->latest('ends_on')->get(),
            'readings' => MeterReading::where('water_provider_id', $provider->id)->with('connection')->latest('reading_date')->limit(100)->get(),
            'bills' => Bill::where('water_provider_id', $provider->id)->with(['consumer', 'period'])->latest()->paginate(20),
        ]);
    }

    public function storeRate(Request $request, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'rates.manage');
        $data = $request->validate([
            'effective_from' => ['required', 'date', Rule::unique('water_rates')->where('water_provider_id', $provider->id)],
            'base_charge' => ['required', 'numeric', 'min:0', 'max:9999999.99', 'decimal:0,2'],
            'unit_rate' => ['required', 'numeric', 'min:0', 'max:999.9999', 'decimal:0,4'],
        ]);
        $rate = WaterRate::create(['water_provider_id' => $provider->id, ...$data]);
        $audit->record($rate, 'rate.created');

        return back()->with('status', 'Rate scheduled.');
    }

    public function storePeriod(Request $request, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'periods.manage');
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'due_on' => ['required', 'date', 'after:ends_on'],
        ]);
        if (BillingPeriod::where('water_provider_id', $provider->id)->where('starts_on', '<=', $data['ends_on'])->where('ends_on', '>=', $data['starts_on'])->exists()) {
            throw ValidationException::withMessages(['starts_on' => 'Billing periods cannot overlap.']);
        }

        $period = BillingPeriod::create(['water_provider_id' => $provider->id, ...$data]);
        $audit->record($period, 'period.created');

        return back()->with('status', 'Billing period created.');
    }

    public function generate(Request $request, ActiveProvider $active, BillingCalculator $calculator, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'bills.generate');
        $data = $request->validate([
            'billing_period_id' => ['required', Rule::exists('billing_periods', 'id')->where('water_provider_id', $provider->id)],
            'meter_reading_id' => ['required', Rule::exists('meter_readings', 'id')->where('water_provider_id', $provider->id)],
        ]);
        $bill = $calculator->generate(
            $provider->id,
            BillingPeriod::whereKey((int) $data['billing_period_id'])->firstOrFail(),
            MeterReading::whereKey((int) $data['meter_reading_id'])->firstOrFail(),
            $request->user()->id,
        );
        $audit->record($bill, 'bill.generated');
        $bill->consumer->notifyAccount('billing', 'New bill', 'Your bill '.$bill->bill_number.' is available.');

        return back()->with('status', 'Bill generated.');
    }

    public function preview(Request $request, ActiveProvider $active, BillingCalculator $calculator): View
    {
        $provider = $active->authorize($request, 'bills.generate');
        $data = $request->validate([
            'billing_period_id' => ['required', Rule::exists('billing_periods', 'id')->where('water_provider_id', $provider->id)],
            'meter_reading_id' => ['required', Rule::exists('meter_readings', 'id')->where('water_provider_id', $provider->id)],
        ]);
        $period = BillingPeriod::whereKey((int) $data['billing_period_id'])->firstOrFail();
        $reading = MeterReading::with('connection.consumer')->whereKey((int) $data['meter_reading_id'])->firstOrFail();
        $snapshot = $calculator->preview($provider->id, $period, $reading);

        return view('pages.billing-preview', compact('provider', 'period', 'reading', 'snapshot'));
    }

    public function batch(Request $request, ActiveProvider $active, BillingCalculator $calculator, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'bills.generate');
        $data = $request->validate(['billing_period_id' => ['required', Rule::exists('billing_periods', 'id')->where('water_provider_id', $provider->id)]]);
        $period = BillingPeriod::whereKey((int) $data['billing_period_id'])->firstOrFail();
        $readings = MeterReading::where('water_provider_id', $provider->id)
            ->whereBetween('reading_date', [$period->starts_on, $period->ends_on])
            ->orderByDesc('reading_date')->get()->unique('water_connection_id');
        $created = 0;

        foreach ($readings as $reading) {
            if (Bill::where('water_connection_id', $reading->water_connection_id)->where('billing_period_id', $period->id)->exists()) {
                continue;
            }

            $bill = $calculator->generate($provider->id, $period, $reading, $request->user()->id);
            $audit->record($bill, 'bill.generated');
            $bill->consumer->notifyAccount('billing', 'New bill', 'Your bill '.$bill->bill_number.' is available.');
            $created++;
        }

        return back()->with('status', $created.' bill(s) generated.');
    }

    public function adjust(Request $request, Bill $bill, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'bills.adjust');
        abort_unless($bill->water_provider_id === $provider->id, 404);
        abort_if($bill->status === 'void', 422);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'between:-9999999.99,9999999.99', 'not_in:0', 'decimal:0,2'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        if ($bill->balanceCents() + DecimalAmount::units((string) $data['amount'], 2) < 0) {
            throw ValidationException::withMessages(['amount' => 'Adjustment cannot create a negative balance.']);
        }

        $adjustment = BillAdjustment::create([
            'water_provider_id' => $provider->id,
            'bill_id' => $bill->id,
            'authorized_by' => $request->user()->id,
            ...$data,
        ]);
        $bill->refreshStatus();
        $audit->record($adjustment, 'bill.adjusted');

        return back()->with('status', 'Adjustment recorded.');
    }

    public function show(Request $request, Bill $bill, ActiveProvider $active): View
    {
        $provider = $active->authorize($request, 'bills.view');
        abort_unless($bill->water_provider_id === $provider->id, 404);

        return view('pages.bill', ['bill' => $bill->load(['consumer', 'connection', 'period', 'adjustments', 'allocations']), 'provider' => $provider]);
    }

    public function void(Request $request, Bill $bill, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'bills.adjust');
        abort_unless($bill->water_provider_id === $provider->id, 404);
        abort_unless($bill->status !== 'void', 422);
        if ($bill->allocations()->whereHas('payment', fn ($query) => $query->where('status', 'verified'))->exists()) {
            throw ValidationException::withMessages(['bill' => 'Reverse verified payments before voiding this bill.']);
        }

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $before = $bill->getAttributes();
        $bill->update(['status' => 'void']);
        $audit->record($bill, 'bill.voided', [...$before, 'void_reason' => $data['reason']]);

        return redirect()->route('provider.billing')->with('status', 'Bill voided.');
    }
}
