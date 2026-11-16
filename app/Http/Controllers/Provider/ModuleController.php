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
use App\Models\SupportTicket;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ModuleController extends Controller
{
    public function tickets(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['open', 'under_review', 'referred_to_provider', 'waiting_for_consumer', 'resolved', 'closed'])],
            'ticket' => ['nullable', 'integer'],
        ]);
        $search = trim($request->string('search')->toString());
        $ticketId = ltrim($search, '#');
        $query = SupportTicket::with('consumer')->where('water_provider_id', $request->user()->water_provider_id)
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('subject', 'like', '%'.$search.'%')
                ->orWhereHas('consumer', fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('account_number', 'like', '%'.$search.'%'))
                ->when(ctype_digit($ticketId), fn ($q) => $q->orWhere('id', $ticketId))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')));
        $tickets = (clone $query)->latest('id')->paginate(20)->appends($request->except('ticket'));
        $selectedTicket = $request->filled('ticket')
            ? (clone $query)->whereKey($request->integer('ticket'))->firstOrFail()
            : $tickets->first();
        $selectedTicket?->load(['messages' => fn ($q) => $q->where('internal', false)->oldest('id')->with('author')]);

        return view('provider.tickets', compact('tickets', 'selectedTicket'));
    }

    public function service(Request $request): View
    {
        $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $connections = Connection::with('consumer')->where('water_provider_id', $request->user()->water_provider_id)
            ->when($request->filled('search'), fn ($q) => $q->whereHas('consumer', fn ($q) => $q->where('name', 'like', '%'.$request->input('search').'%')->orWhere('account_number', 'like', '%'.$request->input('search').'%')))->with('latestServiceEvent')->paginate(20, ['*'], 'connections_page')->withQueryString();
        $bills = Bill::with('allocations')->where('water_provider_id', $request->user()->water_provider_id)->where('status', '!=', 'void')->whereIn('consumer_id', $connections->pluck('consumer_id'))->get()->groupBy('consumer_id');
        $events = ServiceEvent::with('connection.consumer')->whereHas('connection', fn ($q) => $q->where('water_provider_id', $request->user()->water_provider_id))->when($request->filled('search'), fn ($q) => $q->whereHas('connection.consumer', fn ($q) => $q->where('name', 'like', '%'.$request->input('search').'%')->orWhere('account_number', 'like', '%'.$request->input('search').'%')))->latest('id')->paginate(20, ['*'], 'history_page');
        $statusCounts = Connection::where('water_provider_id', $request->user()->water_provider_id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $serviceStatusCounts = [
            'active' => (int) ($statusCounts['active'] ?? 0),
            'disconnected' => (int) ($statusCounts['disconnected'] ?? 0),
            'pending_reconnection' => (int) ($statusCounts['pending_reconnection'] ?? 0),
        ];

        $serviceOptions = [];
        foreach ($connections as $connection) {
            $serviceOptions[$connection->id] = $this->eligibleServiceActions($connection, $bills->get($connection->consumer_id, collect()));
        }

        return view('provider.service', compact('connections', 'bills', 'events', 'serviceOptions', 'serviceStatusCounts'));
    }

    public function serviceAction(Request $request, Connection $connection, AuditLogger $audit): RedirectResponse
    {
        abort_unless($connection->water_provider_id === $request->user()->water_provider_id, 404);
        $data = $request->validate(['action' => ['required', Rule::in(['disconnect', 'reconnect'])], 'reason' => ['nullable', 'string', 'max:2000', 'required_if:action,disconnect']]);
        DB::transaction(function () use ($connection, $data, $request, $audit): void {
            $connection = Connection::whereKey($connection->id)->lockForUpdate()->firstOrFail();
            $bills = Bill::with('allocations')->where('consumer_id', $connection->consumer_id)->where('status', '!=', 'void')->orderBy('id')->lockForUpdate()->get();
            $valid = in_array($data['action'], $this->eligibleServiceActions($connection, $bills), true);
            if (! $valid) {
                throw ValidationException::withMessages(['action' => 'This service action is not eligible. Disconnection requires two outstanding billing months; reconnection requires a cleared balance.']);
            }
            if ($data['action'] === 'disconnect') {
                $notice = ServiceEvent::create(['connection_id' => $connection->id, 'actor_id' => $request->user()->id, 'action' => 'notice', 'reason' => $data['reason']]);
                $audit->record($notice, 'service.notice', $request->user(), after: ['status' => $connection->status]);
                $connection->status = 'disconnected';
                $connection->save();
                $event = ServiceEvent::create(['connection_id' => $connection->id, 'actor_id' => $request->user()->id, 'action' => 'disconnect', 'reason' => 'Disconnected after notice.']);
                $audit->record($event, 'service.disconnect', $request->user(), after: ['status' => $connection->status]);

                return;
            }

            $requestEvent = ServiceEvent::create(['connection_id' => $connection->id, 'actor_id' => $request->user()->id, 'action' => 'request_reconnection', 'reason' => 'Reconnection requested after account balance clearance.']);
            $audit->record($requestEvent, 'service.request_reconnection', $request->user(), after: ['status' => $connection->status]);
            $connection->status = 'active';
            $connection->save();
            $event = ServiceEvent::create(['connection_id' => $connection->id, 'actor_id' => $request->user()->id, 'action' => 'restore', 'reason' => 'Service restored after account balance clearance.']);
            $audit->record($event, 'service.restore', $request->user(), after: ['status' => $connection->status]);
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
        $report = $this->combinedReport($request);

        return view('provider.billing-report', $report);
    }

    public function billingReportCsv(Request $request): StreamedResponse
    {
        $report = $this->combinedReport($request);

        return response()->streamDownload(function () use ($report): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open report output.');
            }
            fputcsv($stream, ['Billing month', 'Readings', 'Usage m³', 'High alerts', 'Low alerts', 'Bills', 'Billed PHP', 'Paid PHP', 'Balance PHP']);
            foreach ($report['rows'] as $row) {
                fputcsv($stream, array_map(static fn ($value): string => preg_match('/^[=+\-@\t\r]/', (string) $value) ? "'".$value : (string) $value, [$row['period']->format('Y-m'), $row['reading_count'], $row['usage'], $row['high_count'], $row['low_count'], $row['bill_count'], $row['billed'], $row['paid'], $row['balance']]));
            }
            fclose($stream);
        }, 'billing-consumption-report-'.$report['range'].'-days.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function billingReportPrint(Request $request): View
    {
        return view('provider.billing-report-print', $this->combinedReport($request));
    }

    public function consumptionReport(): RedirectResponse
    {
        return redirect()->route('provider.reports.billing');
    }

    /** @return array{rows: Collection<int, array<string, mixed>>, kpis: array<string, string|int>, range: int, month: ?string, from: CarbonImmutable, to: CarbonImmutable} */
    private function combinedReport(Request $request): array
    {
        $data = $request->validate(['range' => ['nullable', Rule::in([30, 60, 90])], 'month' => ['nullable', 'date_format:Y-m']]);
        $range = (int) ($data['range'] ?? 30);
        $month = $data['month'] ?? null;
        $from = $month ? CarbonImmutable::parse($month.'-01')->startOfMonth() : CarbonImmutable::today()->subDays($range - 1)->startOfMonth();
        $to = $month ? $from->endOfMonth() : CarbonImmutable::today()->startOfMonth();
        $providerId = $request->user()->water_provider_id;
        $periods = [];
        $addPeriod = function (CarbonImmutable $period) use (&$periods): string {
            $key = $period->format('Y-m');
            $periods[$key] ??= ['period' => $period, 'reading_count' => 0, 'usage' => '0.000', 'high_count' => 0, 'low_count' => 0, 'bill_count' => 0, 'billed' => '0.00', 'paid' => '0.00', 'balance' => '0.00'];

            return $key;
        };
        $readings = MeterReading::where('water_provider_id', $providerId)->whereDate('period_month', '>=', $from)->whereDate('period_month', '<=', $to)
            ->select('meter_readings.*')->selectSub(MeterReading::from('meter_readings as prior')->selectRaw('current_reading - previous_reading')->whereColumn('prior.connection_id', 'meter_readings.connection_id')->whereColumn('prior.period_month', '<', 'meter_readings.period_month')->latest('prior.period_month')->limit(1), 'prior_usage')->get();
        foreach ($readings as $reading) {
            $key = $addPeriod(CarbonImmutable::parse($reading->period_month));
            $usage = bcsub($reading->current_reading, $reading->previous_reading, 3);
            $periods[$key]['reading_count']++;
            $periods[$key]['usage'] = bcadd($periods[$key]['usage'], $usage, 3);
            $prior = $reading->prior_usage === null ? null : bcadd((string) $reading->prior_usage, '0', 3);
            if ($prior !== null && bccomp($prior, '0', 3) > 0) {
                if (bccomp($usage, bcmul($prior, '2', 3), 3) >= 0) {
                    $periods[$key]['high_count']++;
                } elseif (bccomp($usage, bcdiv($prior, '2', 3), 3) <= 0) {
                    $periods[$key]['low_count']++;
                }
            }
        }
        foreach (Bill::with('allocations')->where('water_provider_id', $providerId)->where('status', '!=', 'void')->whereDate('period_month', '>=', $from)->whereDate('period_month', '<=', $to)->get() as $bill) {
            $key = $addPeriod(CarbonImmutable::parse($bill->period_month));
            $periods[$key]['bill_count']++;
            $periods[$key]['billed'] = bcadd($periods[$key]['billed'], $bill->total, 2);
            $periods[$key]['paid'] = bcadd($periods[$key]['paid'], bcsub($bill->total, $bill->balance(), 2), 2);
            $periods[$key]['balance'] = bcadd($periods[$key]['balance'], $bill->balance(), 2);
        }
        $rows = collect($periods)->sortByDesc('period')->values();
        $kpis = [
            'usage' => $rows->reduce(fn (string $total, array $row) => bcadd($total, $row['usage'], 3), '0.000'),
            'bills' => $rows->sum('bill_count'),
            'billed' => $rows->reduce(fn (string $total, array $row) => bcadd($total, $row['billed'], 2), '0.00'),
            'paid' => $rows->reduce(fn (string $total, array $row) => bcadd($total, $row['paid'], 2), '0.00'),
            'balance' => $rows->reduce(fn (string $total, array $row) => bcadd($total, $row['balance'], 2), '0.00'),
        ];

        return compact('rows', 'kpis', 'range', 'month', 'from', 'to');
    }

    /** @param Collection<int, Bill> $bills
     * @return list<string>
     */
    private function eligibleServiceActions(Connection $connection, Collection $bills): array
    {
        $balance = $bills->reduce(fn ($sum, $bill) => bcadd($sum, $bill->balance(), 2), '0.00');
        $outstandingMonths = $bills->filter(fn (Bill $bill) => bccomp($bill->balance(), '0', 2) > 0)->map(fn (Bill $bill) => $bill->period_month->format('Y-m'))->unique()->count();

        return match (true) {
            $connection->status === 'active' && $outstandingMonths >= 2 => ['disconnect'],
            in_array($connection->status, ['disconnected', 'pending_reconnection'], true) && bccomp($balance, '0', 2) === 0 => ['reconnect'],
            default => [],
        };
    }
}
