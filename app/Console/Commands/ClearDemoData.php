<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\ClientNotice;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\ServiceEvent;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\WaterProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearDemoData extends Command
{
    protected $signature = 'demo:clear-data {--force : Confirm removal of demo data}';

    protected $description = 'Remove demo records while keeping the three demo login accounts';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing']) || ! $this->option('force')) {
            $this->error('This command only runs locally or in tests and requires --force.');

            return self::FAILURE;
        }

        $provider = WaterProvider::where('code', 'demo-water')->first();
        $consumer = $provider ? Consumer::where('water_provider_id', $provider->id)->where('account_number', 'DEMO-001')->first() : null;

        if (! $provider || ! $consumer) {
            $this->error('The demo provider or consumer credential record was not found.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($provider, $consumer): void {
            $providerIds = WaterProvider::whereIn('code', ['demo-water', 'riverside-demo'])->pluck('id');
            $consumerIds = Consumer::whereIn('water_provider_id', $providerIds)->pluck('id');
            $userIds = User::whereIn('water_provider_id', $providerIds)->orWhereIn('consumer_id', $consumerIds)->pluck('id');
            $connectionIds = Connection::whereIn('water_provider_id', $providerIds)->pluck('id');
            $billIds = Bill::whereIn('water_provider_id', $providerIds)->pluck('id');

            BillAdjustment::whereIn('bill_id', $billIds)->delete();
            PaymentAllocation::whereIn('bill_id', $billIds)->delete();
            Payment::whereIn('water_provider_id', $providerIds)->delete();
            Bill::whereIn('id', $billIds)->delete();
            ServiceEvent::whereIn('connection_id', $connectionIds)->delete();
            SupportTicket::whereIn('consumer_id', $consumerIds)->delete();
            ClientNotice::whereIn('consumer_id', $consumerIds)->orWhereIn('user_id', $userIds)->delete();
            DB::table('audit_logs')->whereIn('actor_id', $userIds)->delete();
            DB::table('password_reset_tokens')->whereIn('email', User::whereIn('id', $userIds)->pluck('email'))->delete();

            Connection::whereIn('id', $connectionIds)->delete();
            DB::table('water_rates')->whereIn('water_provider_id', $providerIds)->delete();
            DB::table('billing_periods')->whereIn('water_provider_id', $providerIds)->delete();

            User::whereIn('consumer_id', $consumerIds)->where('id', '!=', User::where('email', 'test@example.com')->value('id'))->delete();
            User::whereIn('water_provider_id', $providerIds)->where('id', '!=', User::where('email', 'provider@example.com')->value('id'))->delete();
            Consumer::whereIn('water_provider_id', $providerIds)->where('id', '!=', $consumer->id)->delete();
            WaterProvider::whereIn('id', $providerIds)->where('id', '!=', $provider->id)->delete();
        });

        $this->info('Demo data removed; admin, provider, and consumer credentials were kept.');

        return self::SUCCESS;
    }
}
