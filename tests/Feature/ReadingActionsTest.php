<?php

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Connection;
use App\Models\MeterReading;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\WaterProvider;

test('deleting the latest unbilled reading preserves its audit snapshot and restores the next baseline', function (bool $hasPrevious) {
    $connection = Connection::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $connection->water_provider_id]);
    if ($hasPrevious) {
        MeterReading::factory()->create(['connection_id' => $connection->id, 'period_month' => '2026-09-01', 'current_reading' => '12.125']);
    }
    $reading = MeterReading::factory()->create(['connection_id' => $connection->id, 'period_month' => '2026-10-01', 'previous_reading' => $hasPrevious ? '12.125' : '0.000', 'current_reading' => '20.500', 'recorded_by' => $staff->id]);
    $snapshot = $reading->toArray();
    $page = route('provider.readings.index', ['period' => '2026-10']);

    $this->actingAs($staff)->from($page)->delete('/provider/readings/'.$reading->id)
        ->assertRedirect($page)->assertSessionHas('status', 'Reading deleted.');

    $this->assertDatabaseMissing('meter_readings', ['id' => $reading->id]);
    $log = AuditLog::where('action', 'reading.deleted')->sole();
    expect($log->subject_type)->toBe(MeterReading::class)
        ->and($log->subject_id)->toEqual($reading->id)
        ->and($log->actor_id)->toBe($staff->id)
        ->and($log->before)->toEqual($snapshot);

    $this->post(route('provider.readings.store'), ['connection_id' => $connection->id, 'period_month' => '2026-10', 'current_reading' => '21.000'])->assertSessionHasNoErrors();
    expect(MeterReading::where('connection_id', $connection->id)->whereDate('period_month', '2026-10-01')->sole()->previous_reading)->toBe($hasPrevious ? '12.125' : '0.000');
})->with([true, false]);

test('reading actions reject a newer reading or any linked bill including void bills', function (string $method, string $restriction) {
    $reading = MeterReading::factory()->create(['period_month' => '2026-09-01']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $reading->water_provider_id]);
    if ($restriction === 'newer') {
        MeterReading::factory()->create(['connection_id' => $reading->connection_id, 'period_month' => '2026-10-01']);
    } else {
        Bill::factory()->create(['connection_id' => $reading->connection_id, 'meter_reading_id' => $reading->id, 'status' => $restriction]);
    }

    $this->actingAs($staff)->{$method}('/provider/readings/'.$reading->id, ['current_reading' => '15.000'])
        ->assertRedirect()->assertSessionHasErrors('current_reading');

    expect($reading->fresh()->current_reading)->toBe('10.000');
    $this->assertDatabaseMissing('audit_logs', ['subject_id' => $reading->id, 'action' => 'reading.deleted']);
})->with(['put', 'delete'])->with(['newer', 'unpaid', 'void']);

test('reading actions enforce provider ownership and manage permission', function (string $method) {
    $reading = MeterReading::factory()->create();
    $foreignStaff = User::factory()->waterProvider()->create(['water_provider_id' => WaterProvider::factory()->create()->id]);
    $this->actingAs($foreignStaff)->{$method}('/provider/readings/'.$reading->id, ['current_reading' => '15.000'])->assertNotFound();

    RolePermission::create(['role' => 'water_provider', 'permission' => 'connections.manage', 'allowed' => false]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $reading->water_provider_id]);
    $this->actingAs($staff)->{$method}('/provider/readings/'.$reading->id, ['current_reading' => '15.000'])->assertForbidden();
    $this->assertDatabaseHas('meter_readings', ['id' => $reading->id, 'current_reading' => '10.000']);
})->with(['put', 'delete']);

test('deleting a reading rolls back if its audit cannot be written', function () {
    $reading = MeterReading::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $reading->water_provider_id]);
    AuditLog::creating(function (AuditLog $log): void {
        if ($log->action === 'reading.deleted') {
            throw new RuntimeException('Audit unavailable');
        }
    });
    $this->withoutExceptionHandling()->actingAs($staff);

    expect(fn () => $this->delete('/provider/readings/'.$reading->id))->toThrow(RuntimeException::class, 'Audit unavailable');
    $this->assertDatabaseHas('meter_readings', ['id' => $reading->id]);
});

test('reading history offers edit and delete only for the latest reading of each meter', function () {
    $reading = MeterReading::factory()->create(['previous_reading' => '2.125', 'current_reading' => '10.500']);
    $secondMeter = Connection::factory()->create(['water_provider_id' => $reading->water_provider_id, 'consumer_id' => $reading->connection->consumer_id]);
    $secondReading = MeterReading::factory()->create(['connection_id' => $secondMeter->id]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $reading->water_provider_id]);

    $response = $this->actingAs($staff)->get(route('provider.readings.index'))
        ->assertOk()->assertSee('>Action</th>', false)
        ->assertSee('Reading actions for '.$reading->connection->meter_number)
        ->assertSee('Edit reading')->assertSee('Delete reading')
        ->assertSee('action="'.route('provider.readings.update', $reading).'"', false)
        ->assertSee('action="'.route('provider.readings.destroy', $reading).'"', false)
        ->assertSee('action="'.route('provider.readings.update', $secondReading).'"', false)
        ->assertSee('name="_method" value="PUT"', false)
        ->assertSee('name="_method" value="DELETE"', false)
        ->assertSee('step="0.001"', false)->assertSee('min="2.125"', false)
        ->assertSee('value="10.500"', false)
        ->assertSee($reading->period_month->format('M Y'));

    $html = $response->getContent();
    expect(strpos($html, 'action="'.route('provider.readings.update', $reading).'"'))->toBeGreaterThan(strrpos($html, '</table>'));
});

test('filtered reading history explains disabled actions using all months and linked void bills', function () {
    $old = MeterReading::factory()->create(['period_month' => '2026-09-01']);
    MeterReading::factory()->create(['connection_id' => $old->connection_id, 'period_month' => '2026-10-01']);
    $billed = Bill::factory()->create(['water_provider_id' => $old->water_provider_id, 'status' => 'void']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $old->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.readings.index', ['period' => '2026-09']))
        ->assertOk()->assertSee('A newer reading exists for this meter.')
        ->assertSee('aria-describedby="reading-lock-'.$old->id.'"', false)
        ->assertDontSee('action="'.route('provider.readings.update', $old).'"', false)
        ->assertDontSee('action="'.route('provider.readings.destroy', $old).'"', false);

    $this->get(route('provider.readings.index'))
        ->assertOk()->assertSee('A bill has already been generated for this reading.')
        ->assertDontSee('action="'.route('provider.readings.update', $billed->meter_reading_id).'"', false)
        ->assertDontSee('action="'.route('provider.readings.destroy', $billed->meter_reading_id).'"', false);
});

test('reading history disables management actions for staff without permission and spans its empty table', function () {
    $reading = MeterReading::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $reading->water_provider_id]);
    RolePermission::create(['role' => 'water_provider', 'permission' => 'connections.manage', 'allowed' => false]);

    $this->actingAs($staff)->get(route('provider.readings.index'))
        ->assertOk()->assertSee('You do not have permission to manage readings.')
        ->assertDontSee('action="'.route('provider.readings.update', $reading).'"', false)
        ->assertDontSee('action="'.route('provider.readings.destroy', $reading).'"', false);
    $this->get(route('provider.readings.index', ['search' => 'not-a-consumer']))->assertOk()->assertSee('colspan="6"', false);
});

test('invalid reading edits reopen only their own modal and retain the entered value', function () {
    $reading = MeterReading::factory()->create(['previous_reading' => '2.125', 'current_reading' => '10.500']);
    $other = MeterReading::factory()->create(['water_provider_id' => $reading->water_provider_id]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $reading->water_provider_id]);
    $page = route('provider.readings.index');

    $this->actingAs($staff)->from($page)->put(route('provider.readings.update', $reading), ['_form' => 'edit-reading-'.$reading->id, 'current_reading' => '1.000'])
        ->assertRedirect($page)->assertSessionHasErrors('current_reading');
    session()->reflash();
    session()->save();
    $response = $this->get($page)->assertOk()->assertSee('value="1.000"', false)
        ->assertSee('value="'.$other->current_reading.'"', false);
    expect(substr_count($response->getContent(), 'initiallyOpen: true'))->toBe(1);
    expect($reading->fresh()->current_reading)->toBe('10.500');
});
