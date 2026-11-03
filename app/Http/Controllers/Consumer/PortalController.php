<?php

namespace App\Http\Controllers\Consumer;

use App\Actions\DashboardTrends;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\ClientNotice;
use App\Models\Connection;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\ServiceEvent;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalController extends Controller
{
    public function dashboard(Request $request, DashboardTrends $trends): View
    {
        $consumer = $request->user()->consumer;
        $bills = Bill::where('consumer_id', $consumer->id)->where('status', '!=', 'void');
        $balance = 'total - (select coalesce(sum(amount), 0) from payment_allocations where bill_id = bills.id)';
        $canViewBills = $request->user()->can('billing.view');
        $currentBills = $canViewBills ? (clone $bills)->with('allocations')->whereDate('period_month', today()->startOfMonth()->toDateString())->latest('id')->get() : collect();
        $outstanding = $canViewBills ? (string) (clone $bills)->selectRaw('COALESCE(SUM('.$balance.'), 0) as balance_total')->first()->balance_total : '0.00';
        $upcomingBill = $canViewBills ? (clone $bills)->whereRaw('('.$balance.') > 0')->whereDate('due_at', '>=', today())->orderBy('due_at')->first() : null;
        $overdueBill = $canViewBills ? (clone $bills)->whereRaw('('.$balance.') > 0')->whereDate('due_at', '<', today())->orderBy('due_at')->first() : null;
        $latestReading = MeterReading::with('connection')->whereHas('connection', fn ($q) => $q->where('consumer_id', $consumer->id))->latest('period_month')->latest('id')->first();
        $currentTotal = $currentBills->reduce(fn ($sum, $bill) => bcadd($sum, $bill->total, 2), '0.00');
        $currentBalance = $currentBills->reduce(fn ($sum, $bill) => bcadd($sum, $bill->balance(), 2), '0.00');
        $paymentStatus = $currentBills->isEmpty() ? 'No current bill' : ($currentBills->every(fn ($bill) => $bill->paymentStatus() === 'Paid') ? 'Paid' : ($currentBills->contains(fn ($bill) => $bill->paymentStatus() === 'Overdue') ? 'Overdue' : (bccomp($currentBalance, $currentTotal, 2) < 0 ? 'Partially Paid' : 'Unpaid')));

        $financialTrend = $canViewBills ? $trends->financial($bills) : [];
        $consumptionTrend = $trends->consumption(MeterReading::where('water_provider_id', $consumer->water_provider_id)->whereHas('connection', fn ($q) => $q->where('consumer_id', $consumer->id)));

        return view('consumer.dashboard', compact('consumer', 'currentBills', 'outstanding', 'upcomingBill', 'overdueBill', 'latestReading', 'currentTotal', 'paymentStatus', 'financialTrend', 'consumptionTrend'));
    }

    public function page(Request $request, string $section): View
    {
        abort_unless(in_array($section, ['account', 'connection', 'bills', 'payments', 'notifications', 'faq', 'support'], true), 404);
        $consumer = $request->user()->consumer;
        $data = ['consumer' => $consumer, 'provider' => $consumer->waterProvider];
        if ($section === 'account') {
            $data['connections'] = Connection::where('consumer_id', $consumer->id)->orderBy('id')->limit(10)->get();
        } elseif ($section === 'connection') {
            $request->validate(['connection' => ['nullable', 'integer'], 'month' => ['nullable', 'date_format:Y-m']]);
            if ($request->filled('connection')) {
                Connection::where('consumer_id', $consumer->id)->findOrFail($request->input('connection'));
            }
            $data['connections'] = Connection::with('latestReading')->where('consumer_id', $consumer->id)->orderBy('id')->paginate(10, ['*'], 'connections_page')->withQueryString();
            $data['readings'] = MeterReading::with('connection')->whereHas('connection', fn ($q) => $q->where('consumer_id', $consumer->id))
                ->when($request->filled('connection'), fn ($q) => $q->where('connection_id', $request->integer('connection')))
                ->when($request->filled('month'), fn ($q) => $q->whereDate('period_month', $request->input('month').'-01'))->latest('period_month')->latest('id')->paginate(20, ['*'], 'readings_page')->withQueryString();
            $data['events'] = ServiceEvent::with('connection')->whereHas('connection', fn ($q) => $q->where('consumer_id', $consumer->id))
                ->when($request->filled('connection'), fn ($q) => $q->where('connection_id', $request->integer('connection')))->latest('id')->paginate(10, ['*'], 'events_page')->withQueryString();
        } elseif ($section === 'bills') {
            Gate::authorize('billing.view');
            $data['bills'] = Bill::with('allocations')->where('consumer_id', $consumer->id)->latest('period_month')->latest('id')->paginate(20)->withQueryString();
        } elseif ($section === 'payments') {
            Gate::authorize('payments.view');
            $request->validate(['status' => ['nullable', Rule::in(['pending', 'verified', 'rejected'])], 'bill' => ['nullable', 'integer']]);
            $data['payments'] = Payment::with('bill')->where('consumer_id', $consumer->id)->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))->latest('paid_at')->latest('id')->paginate(20)->withQueryString();
            $data['bills'] = Bill::with('allocations')->where('consumer_id', $consumer->id)->where('status', '!=', 'void')->whereRaw('total > (select coalesce(sum(amount), 0) from payment_allocations where bill_id = bills.id)')->latest('period_month')->limit(50)->get();
            if ($request->filled('bill')) {
                $selected = Bill::with('allocations')->where('consumer_id', $consumer->id)->findOrFail($request->input('bill'));
                if ($selected->status !== 'void' && bccomp($selected->balance(), '0', 2) > 0) {
                    $data['bills']->push($selected);
                    $data['bills'] = $data['bills']->unique('id');
                }
            }
        } elseif ($section === 'notifications') {
            Gate::authorize('notifications.view');
            $request->validate(['unread' => ['nullable', 'boolean']]);
            $data['notices'] = ClientNotice::where('user_id', $request->user()->id)->where('consumer_id', $request->user()->consumer_id)->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))->latest('id')->paginate(20)->withQueryString();
            $data['unreadCount'] = ClientNotice::where('user_id', $request->user()->id)->where('consumer_id', $request->user()->consumer_id)->whereNull('read_at')->count();
        } elseif ($section === 'support') {
            if ($request->user()->can('tickets.view')) {
                $data['tickets'] = SupportTicket::where('consumer_id', $consumer->id)->latest('id')->paginate(20);
            }
        }

        return view('consumer.'.$section, $data);
    }

    public function readNotice(Request $request, ClientNotice $notice): RedirectResponse
    {
        abort_unless($notice->user_id === $request->user()->id && $notice->consumer_id === $request->user()->consumer_id, 404);
        if ($notice->read_at === null) {
            $notice->update(['read_at' => now()]);
        }

        return back()->with('status', 'Notification marked as read.');
    }

    public function readAllNotices(Request $request): RedirectResponse
    {
        ClientNotice::where('user_id', $request->user()->id)->where('consumer_id', $request->user()->consumer_id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }

    public function proof(Request $request, Payment $payment): StreamedResponse
    {
        abort_unless($payment->consumer_id === $request->user()->consumer_id && $payment->proof_path, 404);

        return Storage::disk('local')->download($payment->proof_path);
    }
}
