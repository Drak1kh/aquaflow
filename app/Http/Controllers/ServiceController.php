<?php

namespace App\Http\Controllers;

use App\ActiveProvider;
use App\AuditRecorder;
use App\DecimalAmount;
use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\ServiceAction;
use App\Models\WaterConnection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    public function index(Request $request, ActiveProvider $active): View
    {
        $provider = $active->authorize($request, 'service.manage');

        return view('pages.service', [
            'provider' => $provider,
            'connections' => WaterConnection::where('water_provider_id', $provider->id)->with('consumer')->get(),
            'actions' => ServiceAction::where('water_provider_id', $provider->id)->latest()->limit(50)->get(),
        ]);
    }

    public function updateRules(Request $request, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'provider.manage');
        $data = $request->validate([
            'grace_days' => ['required', 'integer', 'between:0,365'],
            'disconnection_days' => ['required', 'integer', 'between:1,365'],
            'late_fee' => ['required', 'numeric', 'min:0', 'max:9999999.99', 'decimal:0,2'],
            'reconnection_fee' => ['required', 'numeric', 'min:0', 'max:9999999.99', 'decimal:0,2'],
        ]);
        $before = $provider->only(array_keys($data));
        $provider->update($data);
        $audit->record($provider, 'provider.rules.updated', $before);

        return back()->with('status', 'Provider rules updated.');
    }

    public function act(Request $request, WaterConnection $connection, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'service.manage');
        abort_unless($connection->water_provider_id === $provider->id, 404);
        $data = $request->validate([
            'action' => ['required', 'in:notice,disconnect,reconnection_request,approve,restore'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($request, $connection, $provider, $audit, $data): void {
            $connection = WaterConnection::whereKey($connection->id)->lockForUpdate()->firstOrFail();
            $action = $data['action'];
            $lastAction = ServiceAction::where('water_connection_id', $connection->id)->latest('id')->value('action');
            $openBills = Bill::where('water_connection_id', $connection->id)->whereNotIn('status', ['paid', 'void'])->lockForUpdate()->get();
            $unpaid = $openBills->contains(fn (Bill $bill): bool => $bill->balanceCents() > 0);
            $overdueBills = $openBills->filter(fn (Bill $bill): bool => $bill->due_on < now()->subDays($provider->disconnection_days)->toDateString() && $bill->balanceCents() > 0);
            $overdue = $overdueBills->isNotEmpty();

            $allowed = match ($action) {
                'notice' => $connection->status === 'active' && $overdue && $lastAction !== 'notice',
                'disconnect' => $connection->status === 'active' && $lastAction === 'notice' && $overdue,
                'reconnection_request' => $connection->status === 'disconnected' && ! $unpaid,
                'approve' => $connection->status === 'pending_reconnection' && $lastAction === 'reconnection_request',
                'restore' => $connection->status === 'pending_reconnection' && $lastAction === 'approve' && ! $unpaid,
                default => false,
            };
            if (! $allowed) {
                throw ValidationException::withMessages(['action' => 'The service action is not allowed in the current state.']);
            }

            $before = $connection->getAttributes();
            if ($action === 'disconnect') {
                $connection->update(['status' => 'disconnected']);
            } elseif ($action === 'approve') {
                if (DecimalAmount::units((string) $provider->reconnection_fee, 2) > 0) {
                    $feeBill = Bill::where('water_connection_id', $connection->id)->latest('id')->lockForUpdate()->firstOrFail();
                    $adjustment = BillAdjustment::create([
                        'water_provider_id' => $provider->id,
                        'bill_id' => $feeBill->id,
                        'authorized_by' => $request->user()->id,
                        'amount' => $provider->reconnection_fee,
                        'reason' => 'Reconnection fee',
                    ]);
                    $feeBill->refreshStatus();
                    $audit->record($adjustment, 'bill.reconnection_fee.applied');
                }
            } elseif ($action === 'reconnection_request') {
                $connection->update(['status' => 'pending_reconnection']);
            } elseif ($action === 'restore') {
                $connection->update(['status' => 'active']);
            }

            $event = ServiceAction::create([
                'water_provider_id' => $provider->id,
                'water_connection_id' => $connection->id,
                'performed_by' => $request->user()->id,
                'action' => $action,
                'balance_at_action' => $action === 'notice' ? DecimalAmount::format($overdueBills->sum(fn (Bill $bill): int => $bill->balanceCents())) : null,
                'notes' => $data['notes'] ?? null,
            ]);
            $audit->record($event, 'service.'.$action);
            if ($before !== $connection->getAttributes()) {
                $audit->record($connection, 'connection.status.changed', $before);
            }
            $connection->consumer->notifyAccount('service', 'Service update', 'Your connection '.$connection->connection_number.' has a '.$action.' update.');
        });

        return back()->with('status', 'Service action recorded.');
    }

    public function notice(Request $request, ServiceAction $action, ActiveProvider $active): View
    {
        $provider = $active->authorize($request, 'service.manage');
        abort_unless($action->water_provider_id === $provider->id && $action->action === 'notice', 404);
        $connection = WaterConnection::with('consumer')->findOrFail($action->water_connection_id);

        return view('pages.disconnection-notice', compact('provider', 'connection', 'action'));
    }
}
