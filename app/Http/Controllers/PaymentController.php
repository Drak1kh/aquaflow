<?php

namespace App\Http\Controllers;

use App\ActiveProvider;
use App\AuditRecorder;
use App\DecimalAmount;
use App\Models\Bill;
use App\Models\Payment;
use App\PaymentAllocator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function index(Request $request, ActiveProvider $active): View
    {
        $provider = $active->authorize($request, 'payments.view');
        $aging = ['Current' => 0, '1–30 days' => 0, '31–60 days' => 0, '61–90 days' => 0, 'Over 90 days' => 0];
        $openBills = Bill::where('water_provider_id', $provider->id)->where('status', '!=', 'void')->get();
        foreach ($openBills as $bill) {
            $balance = $bill->balanceCents();
            if ($balance === 0) {
                continue;
            }

            if ($bill->due_on >= now()->toDateString()) {
                $bucket = 'Current';
            } else {
                $days = (int) Carbon::parse($bill->due_on)->diffInDays(now());
                $bucket = match (true) {
                    $days <= 30 => '1–30 days',
                    $days <= 60 => '31–60 days',
                    $days <= 90 => '61–90 days',
                    default => 'Over 90 days',
                };
            }
            $aging[$bucket] += $balance;
        }

        return view('pages.payments', [
            'provider' => $provider,
            'payments' => Payment::where('water_provider_id', $provider->id)->with('consumer')->latest()->paginate(20),
            'bills' => Bill::where('water_provider_id', $provider->id)->whereNotIn('status', ['paid', 'void'])->with('consumer')->get(),
            'aging' => $aging,
        ]);
    }

    public function store(Request $request, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'payments.record');
        $data = $request->validate([
            'bill_id' => ['required', Rule::exists('bills', 'id')->where('water_provider_id', $provider->id)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999.99', 'decimal:0,2'],
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'e_wallet', 'other'])],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);
        $bill = Bill::where('water_provider_id', $provider->id)->whereKey((int) $data['bill_id'])->firstOrFail();
        if ($bill->status === 'void' || DecimalAmount::units((string) $data['amount'], 2) > $bill->balanceCents()) {
            throw ValidationException::withMessages(['amount' => 'Amount exceeds the available bill balance.']);
        }

        $payment = Payment::create([
            'water_provider_id' => $provider->id,
            'consumer_id' => $bill->consumer_id,
            'intended_bill_id' => $bill->id,
            'recorded_by' => $request->user()->id,
            'reference_number' => 'P-'.Str::upper(Str::random(12)),
            'amount' => $data['amount'],
            'method' => $data['method'],
            'proof_path' => $request->file('proof')?->store('payment-proofs'),
            'remarks' => $data['remarks'] ?? null,
        ]);
        $audit->record($payment, 'payment.recorded');

        return back()->with('status', 'Payment is pending verification.');
    }

    public function verify(Request $request, Payment $payment, ActiveProvider $active, PaymentAllocator $allocator, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'payments.verify');
        abort_unless($payment->water_provider_id === $provider->id, 404);
        $allocator->verify($payment, $request->user()->id);
        $audit->record($payment->fresh(), 'payment.verified');
        $payment->consumer->notifyAccount('payment', 'Payment verified', 'Payment '.$payment->reference_number.' was verified.');

        return back()->with('status', 'Payment verified and allocated.');
    }

    public function reject(Request $request, Payment $payment, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'payments.verify');
        abort_unless($payment->water_provider_id === $provider->id, 404);
        abort_unless($payment->status === 'pending', 422);
        $data = $request->validate(['remarks' => ['required', 'string', 'max:1000']]);
        $payment->update(['status' => 'rejected', 'remarks' => $data['remarks']]);
        $audit->record($payment, 'payment.rejected');
        $payment->consumer->notifyAccount('payment', 'Payment rejected', 'Payment '.$payment->reference_number.' was rejected.');

        return back()->with('status', 'Payment rejected.');
    }

    public function reverse(Request $request, Payment $payment, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'payments.verify');
        abort_unless($payment->water_provider_id === $provider->id, 404);
        $data = $request->validate(['remarks' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($payment, $data, $audit): void {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless($payment->status === 'verified', 422);
            $payment->update(['status' => 'reversed', 'remarks' => $data['remarks']]);
            foreach ($payment->allocations as $allocation) {
                $bill = Bill::whereKey($allocation->bill_id)->lockForUpdate()->firstOrFail();
                $bill->refreshStatus();
            }
            $audit->record($payment, 'payment.reversed');
        });

        return back()->with('status', 'Payment reversed.');
    }

    public function proof(Request $request, Payment $payment, ActiveProvider $active): BinaryFileResponse|StreamedResponse
    {
        $provider = $active->authorize($request, 'payments.view');
        abort_unless($payment->water_provider_id === $provider->id && $payment->proof_path, 404);

        return Storage::download($payment->proof_path);
    }

    public function receipt(Request $request, Payment $payment, ActiveProvider $active): View
    {
        $provider = $active->authorize($request, 'payments.view');
        abort_unless($payment->water_provider_id === $provider->id && $payment->status === 'verified', 404);

        return view('pages.receipt', ['payment' => $payment->load('consumer', 'allocations.bill'), 'provider' => $provider]);
    }
}
