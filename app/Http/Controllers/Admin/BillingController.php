<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\SupportTicket;
use App\Models\WaterProvider;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $bills = Bill::with(['waterProvider', 'consumer', 'allocations'])
            ->when($request->filled('provider'), fn ($query) => $query->where('water_provider_id', $request->integer('provider')))
            ->when($request->filled('month'), fn ($query) => $query->whereDate('period_month', $request->string('month').'-01'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('id')->paginate(25)->withQueryString();

        return view('admin.billing', [
            'bills' => $bills,
            'providers' => WaterProvider::orderBy('name')->get(),
            'issues' => SupportTicket::with('waterProvider')->whereNotNull('escalated_at')->where('type', 'billing')->latest()->limit(8)->get(),
        ]);
    }

    public function show(Bill $bill): View
    {
        return view('admin.bill', [
            'bill' => $bill->load(['waterProvider', 'consumer', 'allocations']),
            'activity' => AuditLog::where('subject_type', $bill->getMorphClass())->where('subject_id', $bill->id)->latest('id')->get(),
        ]);
    }
}
