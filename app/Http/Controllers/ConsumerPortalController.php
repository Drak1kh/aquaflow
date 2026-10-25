<?php

namespace App\Http\Controllers;

use App\AuditRecorder;
use App\DecimalAmount;
use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\ServiceAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConsumerPortalController extends Controller
{
    private function consumer(Request $request): Consumer
    {
        abort_unless($request->user()->status === 'active', 403);

        return Consumer::where('user_id', $request->user()->id)->where('status', 'active')->with('provider')->firstOrFail();
    }

    public function index(Request $request): View
    {
        $consumer = $this->consumer($request);

        return view('pages.consumer', [
            'consumer' => $consumer,
            'connections' => $consumer->connections()->with(['meter', 'readings'])->get(),
            'bills' => $consumer->bills()->with('period')->latest()->get(),
            'payments' => Payment::where('consumer_id', $consumer->id)->latest()->get(),
            'notifications' => $request->user()->notifications()->latest()->limit(20)->get(),
            'serviceNotices' => ServiceAction::whereIn('water_connection_id', $consumer->connections()->pluck('id'))->where('action', 'notice')->latest()->get(),
        ]);
    }

    public function submitPayment(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $consumer = $this->consumer($request);
        $data = $request->validate([
            'bill_id' => ['required', Rule::exists('bills', 'id')->where('consumer_id', $consumer->id)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999.99', 'decimal:0,2'],
            'method' => ['required', Rule::in(['bank_transfer', 'e_wallet', 'other'])],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $bill = Bill::where('consumer_id', $consumer->id)->whereKey((int) $data['bill_id'])->firstOrFail();
        if ($bill->status === 'void' || DecimalAmount::units((string) $data['amount'], 2) > $bill->balanceCents()) {
            throw ValidationException::withMessages(['amount' => 'Amount exceeds the available bill balance.']);
        }

        $payment = Payment::create([
            'water_provider_id' => $consumer->water_provider_id,
            'consumer_id' => $consumer->id,
            'intended_bill_id' => $bill->id,
            'recorded_by' => $request->user()->id,
            'reference_number' => 'P-'.Str::upper(Str::random(12)),
            'amount' => $data['amount'],
            'method' => $data['method'],
            'proof_path' => $request->file('proof')->store('payment-proofs'),
        ]);
        $audit->record($payment, 'payment.submitted');

        return back()->with('status', 'Payment proof submitted for verification.');
    }

    public function showBill(Request $request, Bill $bill): View
    {
        $consumer = $this->consumer($request);
        abort_unless($bill->consumer_id === $consumer->id, 404);

        return view('pages.bill', ['bill' => $bill->load(['consumer', 'connection', 'period', 'adjustments', 'allocations']), 'provider' => $consumer->provider]);
    }

    public function readNotification(Request $request, string $notification): RedirectResponse
    {
        $entry = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $entry->markAsRead();

        return back();
    }

    public function receipt(Request $request, Payment $payment): View
    {
        $consumer = $this->consumer($request);
        abort_unless($payment->consumer_id === $consumer->id && $payment->status === 'verified', 404);

        return view('pages.receipt', ['payment' => $payment->load('consumer', 'allocations.bill'), 'provider' => $consumer->provider]);
    }

    public function updateContact(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $consumer = $this->consumer($request);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->user()->id)],
            'contact_number' => ['nullable', 'string', 'max:60'],
        ]);
        $before = $consumer->only(['email', 'contact_number']);
        DB::transaction(function () use ($request, $consumer, $data): void {
            $consumer->update($data);
            $user = $request->user();
            if ($user->email !== $data['email']) {
                $user->email = $data['email'];
                $user->email_verified_at = null;
                $user->save();
            }
        });
        $audit->record($consumer, 'consumer.contact.updated', $before);
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'Contact settings updated.');
    }

    public function notice(Request $request, ServiceAction $action): View
    {
        $consumer = $this->consumer($request);
        abort_unless($action->action === 'notice', 404);
        $connection = $consumer->connections()->whereKey($action->water_connection_id)->firstOrFail();

        return view('pages.disconnection-notice', ['provider' => $consumer->provider, 'connection' => $connection, 'action' => $action]);
    }

    public function updatePreferences(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $consumer = $this->consumer($request);
        $request->validate([
            'billing' => ['sometimes', 'boolean'],
            'payment' => ['sometimes', 'boolean'],
            'support' => ['sometimes', 'boolean'],
        ]);
        $before = ['notification_preferences' => $consumer->notification_preferences];
        $consumer->update(['notification_preferences' => [
            'billing' => $request->boolean('billing'),
            'payment' => $request->boolean('payment'),
            'support' => $request->boolean('support'),
        ]]);
        $audit->record($consumer, 'consumer.notification_preferences.updated', $before);

        return back()->with('status', 'Notification preferences updated.');
    }
}
