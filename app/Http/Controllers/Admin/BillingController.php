<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\SupportTicket;
use App\Models\WaterProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['provider' => ['nullable', 'integer', 'exists:water_providers,id'], 'month' => ['nullable', 'date_format:Y-m'], 'status' => ['nullable', Rule::in(['unpaid', 'partially_paid', 'paid', 'overdue', 'void'])]]);
        $bills = Bill::with(['waterProvider', 'consumer', 'allocations', 'adjustments'])
            ->when($request->filled('provider'), fn ($query) => $query->where('water_provider_id', $request->integer('provider')))
            ->when($request->filled('month'), fn ($query) => $query->whereDate('period_month', $request->string('month').'-01'))
            ->when($request->filled('status'), function ($query) use ($request): void {
                $status = $request->input('status');
                $balance = 'total - (select coalesce(sum(amount), 0) from payment_allocations where bill_id = bills.id)';
                if ($status === 'void') {
                    $query->where('status', 'void');

                    return;
                }
                $query->where('status', '!=', 'void');
                if ($status === 'paid') {
                    $query->whereRaw('('.$balance.') <= 0');
                } else {
                    $query->whereRaw('('.$balance.') > 0')->whereDate('due_at', $status === 'overdue' ? '<' : '>=', today());
                    if ($status !== 'overdue') {
                        $query->whereRaw('('.$balance.') '.($status === 'partially_paid' ? '<' : '=').' total');
                    }
                }
            })
            ->latest('id')->paginate(25)->withQueryString();

        return view('admin.billing', [
            'bills' => $bills,
            'providers' => WaterProvider::orderBy('name')->get(),
            'issues' => SupportTicket::with('waterProvider')->whereNotNull('escalated_at')->where('type', 'billing')->when($request->filled('provider'), fn ($q) => $q->where('water_provider_id', $request->integer('provider')))->latest()->limit(8)->get(),
        ]);
    }

    public function show(Bill $bill): View
    {
        return view('admin.bill', [
            'bill' => $bill->load(['waterProvider', 'consumer', 'allocations', 'adjustments']),
            'activity' => AuditLog::where('subject_type', $bill->getMorphClass())->where('subject_id', $bill->id)->latest('id')->get(),
        ]);
    }
}
