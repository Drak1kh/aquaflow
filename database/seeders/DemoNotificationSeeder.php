<?php

namespace Database\Seeders;

use App\Models\ClientNotice;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Seeder;

class DemoNotificationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $client = User::where('email', 'test@example.com')
            ->where('role', UserRole::Consumer)->whereNotNull('consumer_id')->first();

        if (! $client) {
            return;
        }

        $notices = [
            ['welcome', 'Welcome to AquaFlow', 'Your portal brings together your account, connection, bills, and payments.', 'account'],
            ['connection', 'Check your connection details', 'Review your service address and meter information from My Connection.', 'connection'],
            ['billing', 'Your bills in one place', 'Open My Bills to review any available billing statements and balances.', 'bills'],
            ['payments', 'Review your payment history', 'Check payment records and their verification status from Payments.', 'payments'],
            ['support', 'Need help with your water account?', 'Contact your provider through Help & Support for billing or service concerns.', 'support'],
            ['faq', 'Find answers to common questions', 'Browse the FAQs for guidance on bills, payments, and service requests.', 'faq'],
        ];

        foreach ($notices as $index => [$key, $title, $body, $section]) {
            ClientNotice::firstOrCreate([
                'event_key' => 'demo:'.$client->id.':'.$client->consumer_id.':'.$key,
            ], [
                'user_id' => $client->id,
                'consumer_id' => $client->consumer_id,
                'title' => $title,
                'body' => $body,
                'url' => route('portal.section', $section, absolute: false),
                'read_at' => $index < 2 ? now()->subDays(2 - $index) : null,
                'created_at' => now()->subHours((count($notices) - $index) * 3),
            ]);
        }
    }
}
