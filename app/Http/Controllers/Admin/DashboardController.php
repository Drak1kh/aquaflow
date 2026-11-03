<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SupportTicket;
use App\Models\WaterProvider;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $billed = (string) Bill::where('status', '!=', 'void')->sum('total');
        $collected = (string) Payment::where('status', 'verified')->sum('amount');

        return view('admin.dashboard', [
            'providers' => WaterProvider::count(),
            'activeProviders' => WaterProvider::where('is_active', true)->count(),
            'consumers' => Consumer::count(),
            'bills' => Bill::where('status', '!=', 'void')->count(),
            'billed' => $billed,
            'collected' => $collected,
            'outstanding' => bcsub($billed, (string) PaymentAllocation::whereHas('bill', fn ($q) => $q->where('status', '!=', 'void'))->sum('amount'), 2),
            'payments' => Payment::count(),
            'pendingPayments' => Payment::where('status', 'pending')->count(),
            'openTickets' => SupportTicket::whereNotNull('escalated_at')->whereNotIn('status', ['resolved', 'closed'])->count(),
            'recentTickets' => SupportTicket::with(['consumer', 'waterProvider'])->whereNotNull('escalated_at')->whereNotIn('status', ['resolved', 'closed'])->latest('escalated_at')->limit(5)->get(),
            'activity' => AuditLog::latest('id')->limit(8)->get(),
        ]);
    }
}
