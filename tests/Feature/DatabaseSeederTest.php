<?php

use App\Models\Bill;
use App\Models\ClientNotice;
use App\Models\Connection;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use App\UserRole;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Hash;

test('the local seeder creates accounts and linked demo history for every role without duplicates', function () {
    $this->travelTo(now()->setDate(2026, 11, 4)->startOfDay());
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 15);
    $this->assertDatabaseCount('water_providers', 2);
    $this->assertDatabaseCount('consumers', 12);
    $this->assertDatabaseCount('connections', 12);
    $this->assertDatabaseCount('meter_readings', 34);
    $this->assertDatabaseCount('bills', 34);
    $this->assertDatabaseCount('payments', 28);
    $this->assertDatabaseCount('payment_allocations', 24);
    $this->assertDatabaseCount('support_tickets', 12);
    $this->assertDatabaseCount('ticket_messages', 24);
    $this->assertDatabaseCount('service_events', 4);
    expect(User::where('email', 'admin@example.com')->firstOrFail()->role)->toBe(UserRole::SuperAdmin);
    expect(User::where('email', 'provider@example.com')->firstOrFail()->role)->toBe(UserRole::WaterProvider);
    expect(User::where('email', 'test@example.com')->firstOrFail()->role)->toBe(UserRole::Consumer);
    foreach (['admin@example.com' => 'dashboard.admin', 'provider@example.com' => 'dashboard.provider', 'test@example.com' => 'dashboard.consumer'] as $email => $dashboard) {
        $user = User::where('email', $email)->firstOrFail();
        expect(Hash::check('password', $user->password))->toBeTrue();
        $this->actingAs($user)->get(route($dashboard))->assertOk();
    }
});

test('the seeder does not promote an existing account', function () {
    $user = User::factory()->create(['email' => 'admin@example.com']);

    $this->seed(DatabaseSeeder::class);

    expect($user->fresh()->role)->toBe(UserRole::Consumer);
    $this->assertDatabaseCount('users', 15);
});

test('demo bills and payments have consistent balances and provider ownership', function () {
    $this->travelTo(now()->setDate(2026, 11, 4)->startOfDay());

    $this->seed(DatabaseSeeder::class);

    $client = User::where('email', 'test@example.com')->firstOrFail();
    $bill = Bill::where('consumer_id', $client->consumer_id)->whereDate('period_month', '2026-11-01')->firstOrFail();
    expect($bill)->previous_reading->toBe('123.000')->current_reading->toBe('133.000')
        ->consumption->toBe('10.000')->total->toBe('175.00');
    expect($bill->balance())->toBe('100.00');
    expect($bill->paymentStatus())->toBe('Partially Paid');
    $this->assertDatabaseHas('client_notices', ['user_id' => $client->id, 'consumer_id' => $client->consumer_id, 'event_key' => 'due:'.$bill->id.':2026-11-09']);
    $this->assertDatabaseHas('bills', ['status' => 'overdue', 'late_fee' => '25.00']);
    $this->assertDatabaseHas('connections', ['status' => 'disconnected']);
    $this->assertDatabaseHas('support_tickets', ['type' => 'payment', 'status' => 'under_review']);

    foreach (Payment::with(['bill', 'allocations', 'consumer'])->get() as $payment) {
        expect($payment->water_provider_id)->toBe($payment->bill->water_provider_id)->toBe($payment->consumer->water_provider_id);
        expect($payment->consumer_id)->toBe($payment->bill->consumer_id);
        expect($payment->allocations->isNotEmpty())->toBe($payment->status === 'verified');
    }
});

test('rerunning demo seeds preserves changed passwords settings and operational records', function () {
    $this->freezeTime();
    $this->seed(DatabaseSeeder::class);
    $user = User::where('email', 'test@example.com')->firstOrFail();
    $user->update(['name' => 'Edited Client', 'password' => 'my-new-password']);
    $connection = Connection::where('consumer_id', $user->consumer_id)->firstOrFail();
    $connection->update(['status' => 'disconnected']);
    $setting = SystemSetting::findOrFail('platform_name');
    $setting->update(['value' => 'My Water Platform']);
    $noticeCount = ClientNotice::where('user_id', $user->id)->count();

    $this->seed(DatabaseSeeder::class);

    expect($user->fresh()->name)->toBe('Edited Client');
    expect(Hash::check('my-new-password', $user->fresh()->password))->toBeTrue();
    expect($connection->fresh()->status)->toBe('disconnected');
    expect($setting->fresh()->value)->toBe('My Water Platform');
    expect(ClientNotice::where('user_id', $user->id)->count())->toBe($noticeCount);
});

test('demo seeders leave production databases unchanged', function (string $seeder) {
    $environment = app()->environment();
    app()->instance('env', 'production');

    try {
        $this->seed($seeder);
    } finally {
        app()->instance('env', $environment);
    }

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('water_providers', 0);
    $this->assertDatabaseCount('bills', 0);
})->with([DatabaseSeeder::class, DemoDataSeeder::class]);

test('demo seeds do not assign provider data to an existing account with another role', function () {
    $user = User::factory()->create(['email' => 'provider@example.com']);

    $this->seed(DatabaseSeeder::class);

    expect($user->fresh()->role)->toBe(UserRole::Consumer);
    expect($user->fresh()->water_provider_id)->toBeNull();
    $this->assertDatabaseMissing('consumers', ['account_number' => 'DEMO-001']);
    $this->assertDatabaseHas('consumers', ['account_number' => 'RIVER-001']);
});
