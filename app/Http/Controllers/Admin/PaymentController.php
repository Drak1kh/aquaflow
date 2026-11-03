<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\WaterProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['provider' => ['nullable', 'integer', 'exists:water_providers,id'], 'status' => ['nullable', Rule::in(['pending', 'verified', 'rejected'])], 'method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'e_wallet', 'other'])], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $payments = Payment::with(['waterProvider', 'consumer', 'bill'])
            ->when($request->filled('provider'), fn ($query) => $query->where('water_provider_id', $request->integer('provider')))
            ->when($request->filled('method'), fn ($query) => $query->where('method', $request->string('method')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('paid_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('paid_at', '<=', $request->date('to')));
        $collected = (string) (clone $payments)->where('status', 'verified')->sum('amount');
        $payments = $payments->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))->latest('id')->paginate(25)->withQueryString();

        return view('admin.payments', [
            'payments' => $payments,
            'providers' => WaterProvider::orderBy('name')->get(),
            'collected' => $collected,
            'issues' => SupportTicket::with('waterProvider')->whereNotNull('escalated_at')->where('type', 'payment')->when($request->filled('provider'), fn ($q) => $q->where('water_provider_id', $request->integer('provider')))->latest()->limit(8)->get(),
        ]);
    }

    public function show(Payment $payment): View
    {
        return view('admin.payment', [
            'payment' => $payment->load(['waterProvider', 'consumer', 'bill', 'allocations']),
            'activity' => AuditLog::where('subject_type', $payment->getMorphClass())->where('subject_id', $payment->id)->latest('id')->get(),
        ]);
    }

    public function proof(Payment $payment): StreamedResponse
    {
        abort_if($payment->proof_path === null, 404);

        return Storage::disk('local')->download($payment->proof_path);
    }
}
