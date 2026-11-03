<?php

use App\Models\Bill;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaterProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('provider accounts can manage their profile but cannot administer platform users or settings', function () {
    $provider = WaterProvider::factory()->create();
    $admin = User::factory()->superAdmin()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    $this->actingAs($admin)->get(route('dashboard.provider'))->assertOk()->assertSee('Registered consumers');
    $this->get(route('management', 'settings'))->assertOk();
    $this->actingAs($staff)->get(route('dashboard.provider'))->assertOk();
    $this->get(route('management', 'reports'))->assertOk();
    $this->get(route('admin.users.index'))->assertForbidden();
    $this->get(route('management', 'settings'))->assertOk();
    $this->get(route('admin.settings.index'))->assertForbidden();
});

test('consumer proof requires verification and documents are private', function () {
    Storage::fake('local');
    $bill = Bill::factory()->create();
    $consumer = User::factory()->create(['consumer_id' => $bill->consumer_id]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);
    $other = Consumer::factory()->create(['water_provider_id' => $bill->water_provider_id]);
    $otherUser = User::factory()->create(['consumer_id' => $other->id]);

    $this->actingAs($consumer)->post(route('portal.payments.store'), [
        'bill_id' => $bill->id, 'reference' => 'MANUAL-001', 'amount' => '40.00', 'paid_at' => today()->toDateString(), 'proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $payment = Payment::where('reference', 'MANUAL-001')->firstOrFail();
    expect($payment->status)->toBe('pending');
    expect($bill->fresh()->balance())->toBe('150.00');
    Storage::disk('local')->assertExists($payment->proof_path);
    $this->get(route('portal.section', 'payments'))->assertOk()->assertSee('MANUAL-001');
    $this->get(route('portal.section', 'connection'))->assertOk();
    $this->actingAs($staff)->post(route('provider.payments.verify', $payment))->assertSessionHasNoErrors();
    expect($bill->fresh()->balance())->toBe('110.00');
    $this->actingAs($consumer)->get(route('payments.print', $payment))->assertOk()->assertSee('40.00');
    $this->actingAs($otherUser)->get(route('bills.print', $bill))->assertNotFound();
    $this->get(route('payments.print', $payment))->assertNotFound();
});

test('latest unbilled readings can be corrected but finalized readings cannot', function () {
    $connection = Connection::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);
    $reading = MeterReading::factory()->create(['connection_id' => $connection->id, 'water_provider_id' => $connection->water_provider_id, 'previous_reading' => '2.000', 'current_reading' => '10.000']);

    $this->actingAs($staff)->put(route('provider.readings.update', $reading), ['current_reading' => '12.000'])->assertSessionHasNoErrors();
    expect($reading->fresh()->current_reading)->toBe('12.000');
    Bill::factory()->create(['connection_id' => $connection->id, 'meter_reading_id' => $reading->id]);
    $this->put(route('provider.readings.update', $reading), ['current_reading' => '13.000'])->assertSessionHasErrors('current_reading');
    expect($reading->fresh()->current_reading)->toBe('12.000');
});

test('multiple legacy providers block the simplified portal without changing records', function () {
    WaterProvider::factory()->count(2)->create();
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get(route('dashboard.provider'))->assertStatus(409);
    $this->assertDatabaseCount('water_providers', 2);
});

test('consumer contact settings preserve the registered name', function () {
    $consumer = Consumer::factory()->create(['name' => 'Registered Name']);
    $user = User::factory()->create(['consumer_id' => $consumer->id]);
    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', 'Changed Name')
        ->set('phone', '09123456789')
        ->set('email', 'updated@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($consumer->fresh()->name)->toBe('Registered Name');
    expect($consumer->fresh()->phone)->toBe('09123456789');
    expect($consumer->fresh()->email)->toBe('updated@example.com');
});
