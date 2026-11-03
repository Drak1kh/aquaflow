<?php

use App\Models\Bill;
use App\Models\Connection;
use App\Models\Consumer;
use App\Models\ServiceEvent;
use App\Models\User;
use App\Models\WaterProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

test('consumer pages are paginated have descriptive titles and do not query unrelated modules', function () {
    $provider = WaterProvider::factory()->create();
    Consumer::factory()->count(23)->create(['water_provider_id' => $provider->id]);
    $admin = User::factory()->superAdmin()->create();
    DB::enableQueryLog();

    $response = $this->actingAs($admin)->get(route('provider.consumers.index'));

    $response->assertOk()->assertSee('Consumer Management -')->assertViewHas('consumers', fn ($consumers) => $consumers->total() === 23 && $consumers->count() === 20);
    $queries = collect(DB::getQueryLog())->pluck('query')->implode(' ');
    expect($queries)->not->toContain('from "bills"')->not->toContain('from "payments"')->not->toContain('from "meter_readings"');
    expect(substr_count($response->getContent(), 'data-modal="edit-consumer-'))->toBe(0);
});

test('only the selected consumer edit form is rendered and submitted values survive validation', function () {
    $consumer = Consumer::factory()->create();
    $other = Consumer::factory()->create(['water_provider_id' => $consumer->water_provider_id]);
    $admin = User::factory()->superAdmin()->create();
    $page = route('provider.consumers.index', ['edit_consumer' => $consumer->id]);

    $this->actingAs($admin)->get($page)->assertOk()->assertSee('data-modal="edit-consumer-'.$consumer->id.'"', false)->assertDontSee('data-modal="edit-consumer-'.$other->id.'"', false)->assertSee('initiallyOpen: true', false);
    $this->from($page)->put(route('provider.consumers.update', $consumer), ['_form' => 'edit-consumer-'.$consumer->id, 'name' => 'Unsaved revised name', 'email' => 'invalid', 'phone' => '09123456789', 'address' => 'Revised address'])->assertSessionHasErrors('email');
    $this->get($page)->assertOk()->assertSee('value="Unsaved revised name"', false)->assertSee('value="Revised address"', false);
    expect($consumer->fresh()->name)->toBe($consumer->name);
});

test('opening meter readings survive a validation failure and feedback is shown once', function () {
    $connection = Connection::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);
    $page = route('provider.readings.index');

    $this->actingAs($staff)->from($page)->post(route('provider.readings.store'), ['_form' => 'create-reading', 'connection_id' => $connection->id, 'period_month' => '2026-10', 'previous_reading' => '100.000', 'current_reading' => '90.000'])->assertSessionHasErrors('current_reading');
    session()->reflash();
    session()->save();
    $this->get($page)->assertOk()->assertSee('value="100.000"', false);
    $this->assertDatabaseCount('meter_readings', 0);
});

test('locked readings and ineligible service actions are explained instead of offered', function () {
    $bill = Bill::factory()->create(['due_at' => today()->addMonth()]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $bill->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.readings.index'))->assertOk()->assertSee('Locked:')->assertDontSee('action="'.route('provider.readings.update', $bill->meter_reading_id).'"', false);
    $this->get(route('provider.service.index'))->assertOk()->assertSee('No overdue balance:')->assertDontSee('<option value="disconnect">', false)->assertDontSee('<option value="notice">', false);
});

test('search applies to connection tables and service history consistently', function () {
    $connection = Connection::factory()->create();
    $connection->consumer->update(['name' => 'Ada Rivera']);
    $otherConsumer = Consumer::factory()->create(['water_provider_id' => $connection->water_provider_id, 'name' => 'Other Consumer']);
    $other = Connection::factory()->create(['water_provider_id' => $connection->water_provider_id, 'consumer_id' => $otherConsumer->id]);
    ServiceEvent::factory()->create(['connection_id' => $other->id, 'reason' => 'Unrelated history']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.readings.index', ['search' => 'Ada']))->assertOk()->assertSee('Ada Rivera')->assertDontSee('Other Consumer');
    $this->get(route('provider.service.index', ['search' => 'Ada']))->assertOk()->assertSee('Ada Rivera')->assertDontSee('Unrelated history');
});

test('provider pages render one shared validation summary', function () {
    WaterProvider::factory()->create();
    $admin = User::factory()->superAdmin()->create();
    $errors = (new ViewErrorBag)->put('default', new MessageBag(['name' => 'Enter the registered name.']));

    $this->actingAs($admin)->withSession(['errors' => $errors]);
    session()->save();
    $response = $this->get(route('provider.consumers.index'));

    $response->assertOk();
    expect(substr_count($response->getContent(), 'data-form-feedback='))->toBe(1);
});
