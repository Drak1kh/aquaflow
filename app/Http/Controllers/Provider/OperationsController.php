<?php

namespace App\Http\Controllers\Provider;

use App\Actions\AuditLogger;
use App\Actions\BillingService;
use App\Actions\DashboardTrends;
use App\Actions\PaymentService;
use App\Actions\PlatformSettings;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\WaterRate;
use App\Notifications\EscalatedTicketNotification;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsController extends Controller
{
    public function index(Request $request, BillingService $billing, DashboardTrends $trends): View
    {
        $request->validate(['search' => ['nullable', 'string', 'max:255'], 'period' => ['nullable', 'date_format:Y-m'], 'edit_consumer' => ['nullable', 'integer'], 'portal_consumer' => ['nullable', 'integer'], 'reject_payment' => ['nullable', 'integer']]);
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
                'unpaidCount' => (clone $validBills)->whereRaw('total > (select coalesce(sum(amount), 0) from payment_allocations where bill_id = bills.id)')->count(),
                'outstanding' => bcsub((string) $validBills->sum('total'), (string) $paid, 2),
                'monthlyConsumption' => MeterReading::where('water_provider_id', $providerId)->whereDate('period_month', now()->startOfMonth()->toDateString())->selectRaw('COALESCE(SUM(current_reading - previous_reading), 0) as usage_total')->first()->usage_total,
                'collected' => Payment::where('water_provider_id', $providerId)->where('status', 'verified')->sum('amount'),
                'bills' => $request->user()->can('billing.view') ? $bills->with(['consumer', 'allocations'])->latest('id')->limit(5)->get() : collect(),
                'payments' => $request->user()->can('payments.view') ? Payment::where('water_provider_id', $providerId)->latest('id')->limit(5)->get() : collect(),
            ];
        } elseif ($module === 'consumers') {
            $selectedId = $request->input('edit_consumer') ?? $request->input('portal_consumer');
            $selected = $selectedId ? Consumer::with('portalUser')->where('water_provider_id', $providerId)->findOrFail($selectedId) : null;
            $data += ['consumers' => $consumers->with('portalUser')->orderBy('name')->paginate(20)->withQueryString(), 'selectedConsumer' => $selected];
        } elseif ($module === 'readings') {
            $history = MeterReading::with('connection.consumer')->where('water_provider_id', $providerId)
                ->when($request->filled('search'), fn ($q) => $q->whereHas('connection.consumer', fn ($q) => $q->where('name', 'like', $search)->orWhere('account_number', 'like', $search)))
                ->when($request->filled('period'), fn ($q) => $q->whereDate('period_month', $request->input('period').'-01'))
                ->select('meter_readings.*')
                ->selectSub(Bill::selectRaw('count(*)')->whereColumn('meter_reading_id', 'meter_readings.id'), 'bill_count')
                ->selectSub(MeterReading::from('meter_readings as later')->selectRaw('count(*)')->whereColumn('later.connection_id', 'meter_readings.connection_id')->whereColumn('later.period_month', '>', 'meter_readings.period_month'), 'later_count');
            $data += [
                'consumers' => $consumers->orderBy('name')->limit(50)->get(),
                'connections' => $connections->when($request->filled('period'), fn ($q) => $q->whereHas('readings', fn ($q) => $q->whereDate('period_month', $request->input('period').'-01')))->orderBy('number')->paginate(20, ['*'], 'connections_page')->withQueryString(),
                'connectionChoices' => Connection::with('consumer')->where('water_provider_id', $providerId)->when($request->filled('search'), fn ($q) => $q->whereHas('consumer', fn ($q) => $q->where('name', 'like', $search)->orWhere('account_number', 'like', $search)))->orderBy('number')->limit(50)->get(),
                'history' => $history->latest('period_month')->latest('id')->paginate(20, ['*'], 'readings_page')->withQueryString(),
            ];
        } elseif ($module === 'billing') {
            $preview = null;
            if ($request->filled(['connection', 'month'])) {
                $request->validate(['connection' => ['required', 'integer'], 'month' => ['required', 'date_format:Y-m']]);
                $connection = Connection::where('water_provider_id', $providerId)->findOrFail($request->input('connection'));
                $preview = $billing->preview($connection, $request->input('month').'-01');
            }
            $data += [
                'connections' => $connections->orderBy('number')->limit(50)->get(), 'preview' => $preview,
                'periods' => BillingPeriod::where('water_provider_id', $providerId)->latest('period_month')->paginate(12, ['*'], 'periods_page')->withQueryString(),
                'rates' => WaterRate::where('water_provider_id', $providerId)->latest('effective_from')->limit(8)->get(),
                'bills' => $bills->with(['consumer', 'allocations'])->latest('id')->paginate(20)->withQueryString(),
            ];
        } elseif ($module === 'payments') {
            $payments = Payment::with('consumer')->where('water_provider_id', $providerId)->when($request->filled('search'), fn ($q) => $q->whereHas('consumer', fn ($q) => $q->where('name', 'like', $search)->orWhere('account_number', 'like', $search)));
            $data += [
                'payments' => $payments->latest('id')->paginate(20)->withQueryString(),
                'bills' => (clone $bills)->with(['consumer', 'allocations', 'adjustments'])->latest('id')->paginate(20, ['*'], 'bills_page')->withQueryString(),
                'billChoices' => (clone $bills)->with('consumer')->withSum('allocations', 'amount')->where('status', '!=', 'void')->whereRaw('total > (select coalesce(sum(amount), 0) from payment_allocations where bill_id = bills.id)')->latest('id')->limit(50)->get(),
                'selectedPayment' => $request->filled('reject_payment') ? Payment::where('water_provider_id', $providerId)->findOrFail($request->input('reject_payment')) : null,
            ];
        }

        return view('provider.'.$module, $data);
    }

    public function consumer(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'account_number' => ['nullable', 'string', 'max:80', Rule::unique('consumers')->where('water_provider_id', $request->user()->water_provider_id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:2000'],
        ]);
        $data['account_number'] = $data['account_number'] ?? 'C-'.Str::ulid();
        $consumer = Consumer::create([...$data, 'water_provider_id' => $request->user()->water_provider_id]);
        $audit->record($consumer, 'consumer.created', $request->user(), after: $data);

        return back()->with('status', 'Consumer created.');
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
            'previous_reading' => ['nullable', 'decimal:0,3', 'min:0'],
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
            $previous = $lastReading ? $lastReading->current_reading : ($data['previous_reading'] ?? '0.000');

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
            'connection_id' => ['required', Rule::exists('connections', 'id')->where('water_provider_id', $request->user()->water_provider_id)],
            'period_month' => ['required', 'date_format:Y-m'],
        ]);
        $connection = Connection::whereKey($data['connection_id'])->firstOrFail();
        $billing->generate($connection, $data['period_month'].'-01', $request->user());

        return back()->with('status', 'Bill generated.');
    }

    public function batchBills(Request $request, BillingService $billing): RedirectResponse
    {
        $data = $request->validate(['period_month' => ['required', 'date_format:Y-m']]);
        $month = $data['period_month'].'-01';
        $generated = 0;
        $skipped = 0;

        if (! WaterRate::where('water_provider_id', $request->user()->water_provider_id)->whereDate('effective_from', '<=', CarbonImmutable::parse($month)->endOfMonth()->toDateString())->exists()) {
            throw ValidationException::withMessages(['period_month' => 'Configure a water rate before generating bills for this month.']);
        }

        Connection::where('water_provider_id', $request->user()->water_provider_id)->where('status', 'active')
            ->chunkById(100, function ($connections) use ($month, $billing, $request, &$generated, &$skipped): void {
                foreach ($connections as $connection) {
                    if (Bill::where('connection_id', $connection->id)->whereDate('period_month', $month)->exists() || ! MeterReading::where('connection_id', $connection->id)->whereDate('period_month', $month)->exists()) {
                        $skipped++;

                        continue;
                    }
                    $billing->generate($connection, $month, $request->user());
                    $generated++;
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
            $payment->remarks = $data['remarks'];
            $payment->save();
            $audit->record($payment, 'payment.rejected', $request->user(), after: ['remarks' => $data['remarks']]);
        });

        return back()->with('status', 'Payment rejected.');
    }

    public function escalate(Request $request, SupportTicket $ticket, AuditLogger $audit, PlatformSettings $settings): RedirectResponse
    {
        $this->scope($request, $ticket);
        abort_if($ticket->escalated_at !== null, 422);
        $ticket->escalated_at = now();
        $ticket->status = 'under_review';
        $ticket->save();
        $audit->record($ticket, 'ticket.escalated', $request->user());

        if ($settings->get('email_notifications') === '1') {
            User::where('role', UserRole::SuperAdmin->value)->where('is_active', true)
                ->whereNotNull('email')->each(fn (User $admin) => $admin->notify(new EscalatedTicketNotification($ticket->id, $ticket->subject, $ticket->waterProvider->name)));
        }

        return back()->with('status', 'Ticket escalated to Super Admin.');
    }

    public function replyTicket(Request $request, SupportTicket $ticket, AuditLogger $audit): RedirectResponse
    {
        $this->scope($request, $ticket);
        abort_if($ticket->status === 'closed', 422);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $message = $ticket->messages()->create([
            'author_id' => $request->user()->id,
            'body' => $data['body'],
            'attachment_path' => $request->file('attachment')?->store('ticket-attachments'),
            'attachment_name' => $request->file('attachment')?->getClientOriginalName(),
        ]);
        $audit->record($message, 'ticket.message_added', $request->user(), after: ['ticket_id' => $ticket->id]);

        return back()->with('status', 'Response sent.');
    }

    public function attachment(Request $request, TicketMessage $message): StreamedResponse
    {
        $this->scope($request, $message->supportTicket);
        abort_if($message->attachment_path === null, 404);

        return Storage::disk('local')->download($message->attachment_path, $message->attachment_name);
    }

    /** @param Consumer|Payment|SupportTicket $record */
    private function scope(Request $request, $record): void
    {
        abort_unless($record->water_provider_id === $request->user()->water_provider_id, 404);
    }
}
