<?php

namespace App\Http\Controllers\Provider;

use App\Actions\AuditLogger;
use App\Actions\BillingService;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\Connection;
use App\Models\MeterReading;
use App\Models\ServiceEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function service(Request $request): View
    {
        $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $connections = Connection::with('consumer')->where('water_provider_id', $request->user()->water_provider_id)
            ->when($request->filled('search'), fn ($q) => $q->whereHas('consumer', fn ($q) => $q->where('name', 'like', '%'.$request->input('search').'%')->orWhere('account_number', 'like', '%'.$request->input('search').'%')))->with('latestServiceEvent')->paginate(20, ['*'], 'connections_page')->withQueryString();
        $bills = Bill::with('allocations')->where('water_provider_id', $request->user()->water_provider_id)->where('status', '!=', 'void')->whereIn('consumer_id', $connections->pluck('consumer_id'))->get()->groupBy('consumer_id');
        $events = ServiceEvent::with('connection.consumer')->whereHas('connection', fn ($q) => $q->where('water_provider_id', $request->user()->water_provider_id))->when($request->filled('search'), fn ($q) => $q->whereHas('connection.consumer', fn ($q) => $q->where('name', 'like', '%'.$request->input('search').'%')->orWhere('account_number', 'like', '%'.$request->input('search').'%')))->latest('id')->paginate(20, ['*'], 'history_page');

        $serviceOptions = [];
        foreach ($connections as $connection) {
            $serviceOptions[$connection->id] = $this->eligibleServiceActions($connection, $bills->get($connection->consumer_id, collect()), $connection->latestServiceEvent);
        }

        return view('provider.service', compact('connections', 'bills', 'events', 'serviceOptions'));
    }

    public function serviceAction(Request $request, Connection $connection, AuditLogger $audit): RedirectResponse
    {
        abort_unless($connection->water_provider_id === $request->user()->water_provider_id, 404);
        $data = $request->validate(['action' => ['required', Rule::in(['notice', 'disconnect', 'request_reconnection', 'restore'])], 'reason' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($connection, $data, $request, $audit): void {
            $connection = Connection::whereKey($connection->id)->lockForUpdate()->firstOrFail();
            $bills = Bill::with('allocations')->where('consumer_id', $connection->consumer_id)->where('status', '!=', 'void')->orderBy('id')->lockForUpdate()->get();
            $last = ServiceEvent::where('connection_id', $connection->id)->latest('id')->first();
            $valid = in_array($data['action'], $this->eligibleServiceActions($connection, $bills, $last), true);
            if (! $valid) {
                throw ValidationException::withMessages(['action' => 'This service action is not eligible. Disconnection requires an overdue bill and notice; reconnection requires a cleared balance and request.']);
            }
            $connection->status = match ($data['action']) {
                'notice' => 'active', 'disconnect' => 'disconnected', 'request_reconnection' => 'pending_reconnection', 'restore' => 'active',
            };
            $connection->save();
            $event = ServiceEvent::create([...$data, 'connection_id' => $connection->id, 'actor_id' => $request->user()->id]);
            $audit->record($event, 'service.'.$data['action'], $request->user(), after: ['status' => $connection->status]);
        });

        return back()->with('status', 'Service action recorded.');
    }

    public function adjustment(Request $request, Bill $bill, AuditLogger $audit): RedirectResponse
    {
        abort_unless($bill->water_provider_id === $request->user()->water_provider_id, 404);
        $data = $request->validate(['type' => ['required', Rule::in(['credit', 'debit'])], 'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999999'], 'reason' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($bill, $data, $request, $audit): void {
            $bill = Bill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            if ($bill->status === 'void' || ($data['type'] === 'credit' && bccomp($data['amount'], $bill->balance(), 2) > 0)) {
                throw ValidationException::withMessages(['amount' => 'The credit cannot exceed the remaining balance or adjust a void bill.']);
            }
            $bill->total = $data['type'] === 'credit' ? bcsub($bill->total, $data['amount'], 2) : bcadd($bill->total, $data['amount'], 2);
            $bill->status = bccomp($bill->balance(), '0', 2) === 0 ? 'paid' : ($bill->allocations->isNotEmpty() ? 'partially_paid' : 'unpaid');
            $bill->save();
            $adjustment = BillAdjustment::create([...$data, 'bill_id' => $bill->id, 'approved_by' => $request->user()->id]);
            $audit->record($adjustment, 'bill.adjustment_approved', $request->user(), after: $data);
        });

        return back()->with('status', 'Adjustment approved and applied.');
    }

    public function penalty(Request $request, Bill $bill, BillingService $billing): RedirectResponse
    {
        abort_unless($bill->water_provider_id === $request->user()->water_provider_id, 404);
        $billing->applyLateFee($bill);

        return back()->with('status', 'Penalty eligibility checked using the provider grace period and late fee.');
    }

    public function billingReport(Request $request): View
    {
        $request->validate(['group' => ['nullable', Rule::in(['daily', 'monthly', 'yearly'])], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $group = $request->input('group', 'monthly');
        $length = match ($group) {
            'daily' => 10, 'yearly' => 4, default => 7
        };
        $expression = 'SUBSTR(created_at, 1, '.$length.')';
        $rows = Bill::where('water_provider_id', $request->user()->water_provider_id)->where('status', '!=', 'void')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->selectRaw($expression.' as report_period, COUNT(*) as bill_count, SUM(total) as charges')
            ->groupByRaw($expression)->orderBy('report_period')->paginate(24)->withQueryString();

        return view('provider.billing-report', compact('rows', 'group'));
    }

    public function consumptionReport(Request $request): View
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'search' => ['nullable', 'string', 'max:255']]);
        $readings = MeterReading::with('connection.consumer')->where('water_provider_id', $request->user()->water_provider_id)
            ->when($request->filled('search'), fn ($q) => $q->whereHas('connection.consumer', fn ($q) => $q->where('name', 'like', '%'.$request->input('search').'%')->orWhere('account_number', 'like', '%'.$request->input('search').'%')))
            ->when($request->filled('month'), fn ($q) => $q->whereDate('period_month', $request->input('month').'-01'))
            ->select('meter_readings.*')->selectSub(MeterReading::from('meter_readings as prior')->selectRaw('current_reading - previous_reading')->whereColumn('prior.connection_id', 'meter_readings.connection_id')->whereColumn('prior.period_month', '<', 'meter_readings.period_month')->latest('prior.period_month')->limit(1), 'prior_usage')
            ->latest('period_month')->latest('id')->paginate(20)->withQueryString();
        $rows = $readings->getCollection()->map(function (MeterReading $reading): array {
            $usage = bcsub($reading->current_reading, $reading->previous_reading, 3);
            $prior = $reading->prior_usage === null ? null : bcadd((string) $reading->prior_usage, '0', 3);
            $flag = $prior !== null && bccomp($prior, '0', 3) > 0 ? (bccomp($usage, bcmul($prior, '2', 3), 3) >= 0 ? 'Unusually high' : (bccomp($usage, bcdiv($prior, '2', 3), 3) <= 0 ? 'Unusually low' : 'Typical')) : 'No comparison';

            return compact('reading', 'usage', 'prior', 'flag');
        });

        return view('provider.consumption-report', compact('rows', 'readings'));
    }

    /** @param Collection<int, Bill> $bills
     * @return list<string>
     */
    private function eligibleServiceActions(Connection $connection, Collection $bills, ?ServiceEvent $last): array
    {
        $balance = $bills->reduce(fn ($sum, $bill) => bcadd($sum, $bill->balance(), 2), '0.00');
        $overdue = $bills->contains(fn ($bill) => $bill->due_at->isBefore(today()) && bccomp($bill->balance(), '0', 2) > 0);

        return match (true) {
            $connection->status === 'active' && $overdue => [$last?->action === 'notice' ? 'disconnect' : 'notice'],
            $connection->status === 'disconnected' && bccomp($balance, '0', 2) === 0 => ['request_reconnection'],
            $connection->status === 'pending_reconnection' && bccomp($balance, '0', 2) === 0 => ['restore'],
            default => [],
        };
    }
}
