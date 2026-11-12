<?php

namespace Database\Seeders;

use App\Actions\BillingService;
use App\Actions\PaymentService;
use App\Actions\PermissionRegistry;
use App\Actions\PlatformSettings;
use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\BillingPeriod;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\RolePermission;
use App\Models\ServiceEvent;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\WaterProvider;
use App\Models\WaterRate;
use App\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FullDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        DB::transaction(function (): void {
            $this->call(DemoDataSeeder::class);

            foreach (PlatformSettings::DEFAULTS as $key => $value) {
                SystemSetting::firstOrCreate(['key' => $key], ['value' => $value]);
            }
            foreach (PermissionRegistry::DEFAULTS as $role => $permissions) {
                foreach (array_keys(PermissionRegistry::LABELS) as $permission) {
                    RolePermission::firstOrCreate(['role' => $role, 'permission' => $permission], [
                        'allowed' => in_array($permission, $permissions, true),
                    ]);
                }
            }

            foreach (['demo-water' => 'AquaFlow Waterworks', 'demo-riverside' => 'Riverside Water Cooperative'] as $code => $name) {
                $provider = WaterProvider::firstOrCreate(['code' => $code], [
                    'name' => $name, 'email' => 'riverside@example.com', 'phone' => '0917 000 0200',
                    'address' => 'Riverside service center', 'is_active' => true,
                    'due_days' => 14, 'grace_days' => 7, 'late_fee' => '25.00',
                ]);
                $actor = User::firstOrCreate(['email' => $code === 'demo-water' ? 'provider@example.com' : 'riverside@example.com'], [
                    'name' => $name.' Provider', 'role' => UserRole::WaterProvider,
                    'water_provider_id' => $provider->id, 'password' => 'password', 'email_verified_at' => now(),
                ]);
                WaterRate::firstOrCreate(['water_provider_id' => $provider->id, 'effective_from' => today()->startOfMonth()->subMonths(6)->toDateString()], [
                    'base_charge' => '50.00', 'unit_rate' => '10.00',
                ]);

                for ($index = 1; $index <= 12; $index++) {
                    $this->seedConsumer($provider, $actor, $index);
                }
            }
            BillAdjustment::where('reason', 'Demo account service charge')->where('type', 'charge')->update(['type' => 'debit']);
            $this->call([DemoNotificationSeeder::class, ClientNoticeSeeder::class]);
        });
    }

    private function seedConsumer(WaterProvider $provider, User $actor, int $index): void
    {
        $account = 'DEMO-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT);
        $consumer = Consumer::firstOrCreate(['water_provider_id' => $provider->id, 'account_number' => $account], [
            'name' => ['Maria Santos', 'Juan Dela Cruz', 'Ana Reyes', 'Jose Garcia', 'Rosa Mendoza', 'Carlo Ramos', 'Liza Torres', 'Pedro Flores', 'Grace Aquino', 'Mark Villanueva', 'Elena Castillo', 'Paolo Navarro'][$index - 1],
            'email' => $provider->code.'-consumer'.$index.'@example.com',
            'phone' => '0917 555 '.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'address' => $index.' Mabini Street, Barangay Riverside', 'is_active' => true,
        ]);
        $connection = Connection::firstOrCreate(['water_provider_id' => $provider->id, 'number' => $provider->code.'-CON-'.$index], [
            'consumer_id' => $consumer->id, 'meter_number' => $provider->code.'-MTR-'.$index,
            'status' => 'active', 'installed_at' => today()->subYear()->toDateString(),
        ]);

        $previous = 100 + $index * 10;
        for ($monthsAgo = 5; $monthsAgo >= 0; $monthsAgo--) {
            $month = today()->startOfMonth()->subMonths($monthsAgo);
            $consumption = 8 + (($index + $monthsAgo) % 16);
            $reading = MeterReading::firstOrCreate(['connection_id' => $connection->id, 'period_month' => $month->toDateString()], [
                'water_provider_id' => $provider->id, 'previous_reading' => $previous,
                'current_reading' => $previous + $consumption, 'recorded_by' => $actor->id,
            ]);
            $previous = (int) $reading->current_reading;
            $period = BillingPeriod::firstOrCreate(['water_provider_id' => $provider->id, 'period_month' => $month->toDateString()], [
                'due_at' => $month->addDays(20)->toDateString(),
            ]);
            $bill = Bill::where('connection_id', $connection->id)->whereDate('period_month', $month)->first();
            if ($bill) {
                continue;
            }

            $bill = app(BillingService::class)->generate($connection, $month->toDateString(), $period->due_at->toDateString(), $actor);
            $bill->update(['created_at' => $month->addDays(1)]);

            if ($monthsAgo >= 3 || ($monthsAgo === 1 && $index % 3 === 0)) {
                $this->seedPayment($bill, $actor, $bill->total, true);
            } elseif ($monthsAgo === 2) {
                $this->seedPayment($bill, $actor, bcdiv($bill->total, '2', 2), true);
            } elseif ($monthsAgo === 0 && $index % 3 !== 0) {
                $this->seedPayment($bill, $actor, $bill->total, false, $index % 3 === 2);
            }

            if ($monthsAgo > 0) {
                app(BillingService::class)->applyLateFee($bill);
            }
            if ($monthsAgo === 0 && $index === 1) {
                BillAdjustment::create(['bill_id' => $bill->id, 'approved_by' => $actor->id,
                    'type' => 'debit', 'amount' => '25.00', 'reason' => 'Demo account service charge']);
                $bill->update(['total' => bcadd($bill->total, '25.00', 2)]);
            }
        }

        if ($index % 4 === 0) {
            $notice = ServiceEvent::firstOrCreate(['connection_id' => $connection->id, 'action' => 'notice', 'reason' => 'Demo overdue account: please settle outstanding bills.'], ['actor_id' => $actor->id]);
            if ($index === 12) {
                ServiceEvent::firstOrCreate(['connection_id' => $connection->id, 'action' => 'disconnect', 'reason' => 'Demo service suspension following overdue notice.'], ['actor_id' => $actor->id]);
                if ($notice->wasRecentlyCreated) {
                    $connection->update(['status' => 'disconnected']);
                }
            }
        }
        if ($index <= 4) {
            $this->seedTicket($consumer, $actor, $index);
        }
    }

    private function seedPayment(Bill $bill, User $actor, string $amount, bool $verified, bool $rejected = false): void
    {
        $payment = Payment::create([
            'water_provider_id' => $bill->water_provider_id, 'consumer_id' => $bill->consumer_id,
            'bill_id' => $bill->id, 'reference' => 'DEMO-PAY-'.$bill->id, 'amount' => $amount,
            'method' => $bill->id % 2 === 0 ? 'cash' : 'bank_transfer',
            'status' => $rejected ? 'rejected' : 'pending',
            'remarks' => $rejected ? 'Demo payment: the reference could not be verified. Please resubmit.' : 'Demo payment record',
            'paid_at' => $bill->period_month->addDays(10)->min(today())->toDateString(),
            'created_at' => $bill->period_month->addDays(10)->min(now()),
        ]);
        if ($verified) {
            app(PaymentService::class)->verify($payment, $actor);
        }
    }

    private function seedTicket(Consumer $consumer, User $actor, int $index): void
    {
        $status = ['open', 'under_review', 'resolved', 'under_review'][$index - 1];
        $ticket = SupportTicket::firstOrCreate(['consumer_id' => $consumer->id, 'subject' => 'Demo: '.['Question about my latest bill', 'Low water pressure', 'Payment reference clarification', 'Billing dispute review'][$index - 1]], [
            'water_provider_id' => $consumer->water_provider_id, 'opened_by' => $consumer->portalUser?->id,
            'assigned_to' => $actor->id, 'type' => $index % 2 === 0 ? 'service' : 'billing',
            'description' => 'Sample support concern for reviewing the provider and consumer support workflow.',
            'status' => $status, 'escalated_at' => $index === 4 ? now()->subDay() : null,
            'resolved_at' => $status === 'resolved' ? now()->subDay() : null,
            'resolution' => $status === 'resolved' ? 'The payment reference has been explained to the consumer.' : null,
        ]);
        if (in_array($ticket->status, ['in_progress', 'escalated'], true)) {
            $ticket->update(['status' => $status]);
        }
        TicketMessage::firstOrCreate(['support_ticket_id' => $ticket->id, 'author_id' => $actor->id, 'body' => 'Thank you for contacting us. We are reviewing your account concern.'], ['internal' => false]);
    }
}
