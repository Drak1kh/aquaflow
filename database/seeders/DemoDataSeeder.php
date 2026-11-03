<?php

namespace Database\Seeders;

use App\Actions\BillingService;
use App\Actions\ClientNoticeService;
use App\Actions\PaymentService;
use App\Actions\PlatformSettings;
use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\ServiceEvent;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WaterProvider;
use App\Models\WaterRate;
use App\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        DB::transaction(function (): void {
            $this->account(['name' => 'AquaFlow Administrator', 'email' => 'admin@example.com', 'role' => UserRole::SuperAdmin]);
            foreach (PlatformSettings::DEFAULTS as $key => $value) {
                SystemSetting::firstOrCreate(['key' => $key], ['value' => $value]);
            }

            $this->provider('demo-water', 'San Isidro Waterworks (Demo)', 'provider@example.com', 'DEMO', [
                'Maria Santos', 'Juan Dela Cruz', 'Ana Reyes', 'Carlo Mendoza', 'Liza Garcia', 'Ramon Flores',
            ]);
            $this->provider('riverside-demo', 'Riverside Water Services (Demo)', 'riverside.provider@example.com', 'RIVER', [
                'Elena Ramos', 'Miguel Torres', 'Sofia Aquino', 'Paolo Cruz', 'Rosa Castillo', 'Luis Navarro',
            ]);
        });
    }

    /** @param array<string, mixed> $attributes */
    private function account(array $attributes): User
    {
        return User::where('email', $attributes['email'])->first() ?? User::factory()->create($attributes);
    }

    /** @param list<string> $names */
    private function provider(string $code, string $name, string $email, string $prefix, array $names): void
    {
        $provider = WaterProvider::firstOrCreate(['code' => $code], [
            'name' => $name, 'email' => $email, 'phone' => '02 8555 0100',
            'address' => '12 Waterworks Road, Barangay San Isidro (Demo office)',
            'due_days' => 14, 'grace_days' => 7, 'late_fee' => '25.00', 'is_active' => true,
        ]);
        $staff = $this->account(['name' => $name.' Staff', 'email' => $email, 'role' => UserRole::WaterProvider, 'water_provider_id' => $provider->id]);
        if ($staff->role !== UserRole::WaterProvider || $staff->water_provider_id !== $provider->id) {
            return;
        }

        WaterRate::where('water_provider_id', $provider->id)->whereDate('effective_from', '2020-01-01')->firstOrCreate([], [
            'water_provider_id' => $provider->id, 'effective_from' => '2020-01-01', 'base_charge' => '50.00', 'unit_rate' => '12.50',
        ]);
        foreach ([2, 1, 0] as $monthsAgo) {
            $month = today()->startOfMonth()->subMonths($monthsAgo);
            BillingPeriod::where('water_provider_id', $provider->id)->whereDate('period_month', $month->toDateString())->firstOrCreate([], [
                'water_provider_id' => $provider->id, 'period_month' => $month->toDateString(),
                'due_at' => $monthsAgo === 0 ? today()->addDays(5)->toDateString() : $month->copy()->addDays(19)->toDateString(),
            ]);
        }

        foreach ($names as $index => $consumerName) {
            $accountNumber = $prefix.'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $consumerEmail = $prefix === 'DEMO' && $index === 0 ? 'test@example.com' : strtolower($prefix).'.client'.($index + 1).'@example.com';
            $consumer = Consumer::firstOrCreate(['water_provider_id' => $provider->id, 'account_number' => $accountNumber], [
                'name' => $consumerName, 'email' => $consumerEmail, 'phone' => '0917 555 '.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'address' => ($index + 10).' Sampaguita Street, Barangay '.($prefix === 'DEMO' ? 'San Isidro' : 'Riverside').' (Demo address)', 'is_active' => true,
            ]);
            if (! $consumer->portalUser()->exists()) {
                $this->account(['name' => $consumer->name, 'email' => $consumerEmail, 'role' => UserRole::Consumer, 'consumer_id' => $consumer->id]);
            }
            $connection = Connection::firstOrCreate(['water_provider_id' => $provider->id, 'number' => $prefix.'-CON-'.($index + 1)], [
                'consumer_id' => $consumer->id, 'meter_number' => $prefix.'-MTR-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                'status' => 'active', 'installed_at' => today()->subYear()->toDateString(),
            ]);
            if ($connection->consumer_id !== $consumer->id) {
                continue;
            }
            $newConnection = $connection->wasRecentlyCreated;
            $this->billingHistory($connection, $consumer, $staff, $index);
            $this->support($consumer, $staff, $index);
            if ($index === 5 && $newConnection) {
                foreach (['notice' => 'Demo notice: overdue bills remain unpaid.', 'disconnect' => 'Demo service suspension after the unpaid notice.'] as $action => $reason) {
                    ServiceEvent::create(['connection_id' => $connection->id, 'actor_id' => $staff->id, 'action' => $action, 'reason' => $reason]);
                }
                $connection->update(['status' => 'disconnected']);
                app(ClientNoticeService::class)->send($consumer, 'demo:disconnected:'.$connection->id, 'Connection update', 'This demonstration connection is disconnected because of unpaid bills.', route('portal.section', ['section' => 'connection'], false));
            }
        }
    }

    private function billingHistory(Connection $connection, Consumer $consumer, User $staff, int $index): void
    {
        $billing = app(BillingService::class);
        $notices = app(ClientNoticeService::class);
        foreach ([2, 1, 0] as $monthsAgo) {
            if ($monthsAgo === 0 && $index === 5) {
                continue;
            }
            $month = today()->startOfMonth()->subMonths($monthsAgo)->toDateString();
            $bill = Bill::where('connection_id', $connection->id)->whereDate('period_month', $month)->first();
            if ($bill !== null || $connection->status !== 'active') {
                continue;
            }
            $previous = MeterReading::where('connection_id', $connection->id)->whereDate('period_month', '<', $month)->latest('period_month')->first()?->current_reading ?? '100.000';
            MeterReading::where('connection_id', $connection->id)->whereDate('period_month', $month)->firstOrCreate([], [
                'connection_id' => $connection->id, 'period_month' => $month,
                'water_provider_id' => $connection->water_provider_id, 'previous_reading' => $previous,
                'current_reading' => bcadd($previous, (string) (10 + $index * 2 + $monthsAgo), 3), 'recorded_by' => $staff->id,
            ]);
            $bill = $billing->generate($connection, $month, $staff);
            $bill->update(['created_at' => $month, 'updated_at' => $month]);
            if ($monthsAgo === 2 || ($monthsAgo === 1 && ! in_array($index, [2, 5], true)) || ($monthsAgo === 0 && $index === 1)) {
                $this->payment($bill, $staff, $bill->total, 'verified');
            } elseif ($monthsAgo === 0 && in_array($index, [0, 3, 4], true)) {
                $this->payment($bill, $staff, $index === 0 ? '75.00' : $bill->total, match ($index) {
                    0 => 'verified', 3 => 'pending', 4 => 'rejected',
                });
            }
            $billing->applyLateFee($bill);
            $bill->refresh();
            $notices->send($consumer, 'bill:'.$bill->id, 'Water bill available', 'Demo bill '.$bill->number.' for '.$bill->period_month->format('M Y').' is available.', route('bills.print', $bill, false));
            $notices->remind($bill);
        }
    }

    private function payment(Bill $bill, User $staff, string $amount, string $status): void
    {
        $payment = Payment::firstOrCreate(['water_provider_id' => $bill->water_provider_id, 'reference' => 'DEMO-PAY-'.$bill->number], [
            'consumer_id' => $bill->consumer_id, 'bill_id' => $bill->id, 'amount' => $amount,
            'method' => $status === 'verified' ? 'cash' : 'bank_transfer', 'status' => $status === 'verified' ? 'pending' : $status,
            'paid_at' => min(today()->toDateString(), $bill->period_month->copy()->addDays(9)->toDateString()),
            'remarks' => $status === 'rejected' ? 'Demo rejection: the reference could not be matched to a received transfer.' : 'Demonstration payment only; no real funds were transferred.',
        ]);
        if ($payment->wasRecentlyCreated && $status === 'verified') {
            app(PaymentService::class)->verify($payment, $staff);
            $payment->refresh();
        }
        app(ClientNoticeService::class)->send($bill->consumer, 'payment:'.$payment->id.':'.$payment->status, match ($payment->status) {
            'verified' => 'Payment confirmed', 'rejected' => 'Payment rejected', default => 'Payment awaiting verification',
        }, 'Demo payment '.$payment->reference.' · ₱'.$payment->amount.'. '.$payment->remarks, route('portal.section', ['section' => 'payments'], false));
    }

    private function support(Consumer $consumer, User $staff, int $index): void
    {
        $type = match ($index % 3) {
            0 => 'billing', 1 => 'payment', 2 => 'general',
        };
        $ticket = SupportTicket::firstOrCreate(['consumer_id' => $consumer->id, 'subject' => 'Demo: '.match ($type) {
            'billing' => 'Please review my water charges', 'payment' => 'Please check my payment reference', default => 'How do I read my water meter?',
        }], [
            'water_provider_id' => $consumer->water_provider_id, 'opened_by' => $consumer->portalUser?->id,
            'type' => $type, 'description' => 'This is a sample support request for reviewing the AquaFlow workflow.',
            'status' => $index < 2 ? 'under_review' : ($index === 2 ? 'resolved' : 'open'),
            'escalated_at' => $index < 2 ? now()->subDay() : null,
            'resolved_at' => $index === 2 ? now() : null,
            'resolution' => $index === 2 ? 'The consumer received instructions for reading the meter.' : null,
        ]);
        $ticket->messages()->firstOrCreate(['body' => 'Demo response: we have received your request and are reviewing your account.', 'internal' => false], ['author_id' => $staff->id]);
        $ticket->messages()->firstOrCreate(['body' => 'Demo internal note: check the original ledger before responding.', 'internal' => true], ['author_id' => $staff->id]);
        app(ClientNoticeService::class)->send($consumer, 'demo:support:'.$ticket->id, 'Support request update', 'Your sample request has a provider response.', route('portal.tickets.show', $ticket, false));
    }
}
