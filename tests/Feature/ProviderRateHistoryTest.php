<?php

use App\Models\User;
use App\Models\WaterProvider;
use App\Models\WaterRate;

function rateHistoryChart(string $html): array
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);

    return [$xpath, $xpath->query('//figure[@aria-label="Unit-rate history"]')->item(0)];
}

test('billing rate history uses a fixed linear hundred peso scale and preserves actual values', function () {
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    foreach (['999.00', '0.00', '10.00', '20.00', '40.00', '80.00', '125.00'] as $index => $unitRate) {
        WaterRate::factory()->create([
            'water_provider_id' => $provider->id,
            'effective_from' => sprintf('2026-%02d-01', $index + 1),
            'unit_rate' => $unitRate,
            'base_charge' => '777.00',
        ]);
    }

    $response = $this->actingAs($staff)->get(route('provider.billing.index'))->assertOk();
    [$xpath, $chart] = rateHistoryChart($response->getContent());

    expect($chart)->not->toBeNull();
    expect($xpath->query('ancestor::section[1]', $chart)->item(0)->getAttribute('class'))
        ->toBe('grid gap-4 sm:grid-cols-2');
    expect($xpath->query('.//*[@aria-label="Unit rate scale"]', $chart)->item(0)->getAttribute('class'))
        ->toContain('h-40');
    expect($xpath->query('.//*[@role="list"]/..', $chart)->item(0)->getAttribute('class'))
        ->toContain('h-40');
    expect($chart->textContent)->toContain('Unit rate (₱ / m³)')->not->toContain('777.00')->not->toContain('999.00');
    $axis = $xpath->query('.//*[@aria-label="Unit rate scale"]/span', $chart);
    expect(array_map(fn ($tick) => trim($tick->textContent), iterator_to_array($axis)))
        ->toBe(['₱100.00', '₱80.00', '₱60.00', '₱40.00', '₱20.00', '₱10.00', '₱0.00']);
    expect(array_map(fn ($tick) => $tick->getAttribute('style'), iterator_to_array($axis)))
        ->toBe(['top: 0%', 'top: 20%', 'top: 40%', 'top: 60%', 'top: 80%', 'top: 90%', 'top: 100%']);

    $bars = $xpath->query('.//*[@role="listitem"]', $chart);
    expect($bars)->toHaveCount(6);
    foreach (['0.00', '10.00', '20.00', '40.00', '80.00', '125.00'] as $index => $unitRate) {
        $bar = $bars->item($index);
        expect($bar->textContent)->toContain('₱'.$unitRate);
        expect($bar->getAttribute('aria-label'))->toBe(date('F Y', strtotime(sprintf('2026-%02d-01', $index + 2))).' — ₱'.$unitRate.' / m³');
        expect($xpath->query('./div[@aria-hidden="true"]', $bar)->item(0)->getAttribute('style'))
            ->toBe('height: '.min(100, (float) $unitRate).'%');
    }
});

test('billing rate history shows three empty consecutive months from the current month', function () {
    $this->travelTo(now()->setDate(2026, 12, 15));
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    $response = $this->actingAs($staff)->get(route('provider.billing.index'))->assertOk()
        ->assertSee('No rate records yet.');
    [$xpath, $chart] = rateHistoryChart($response->getContent());
    $months = $xpath->query('.//dl[@aria-label="Monthly unit rates"]/div', $chart);

    expect($months)->toHaveCount(3);
    foreach (['Dec 2026', 'Jan 2027', 'Feb 2027'] as $index => $month) {
        expect($xpath->query('./dt', $months->item($index))->item(0)->textContent)->toBe($month);
        expect($xpath->query('./dd', $months->item($index))->item(0)->textContent)->toBe('No rate record');
    }
    expect($xpath->query('.//*[@role="listitem"]/div[@aria-hidden="true"]', $chart))->toHaveCount(0);
});

test('billing rate history pads a sole December record into January and February', function () {
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
    WaterRate::factory()->create([
        'water_provider_id' => $provider->id,
        'effective_from' => '2026-12-10',
        'unit_rate' => '10.00',
        'base_charge' => '50.00',
    ]);

    $response = $this->actingAs($staff)->get(route('provider.billing.index'))->assertOk()
        ->assertSee('Effective from: December 10, 2026')->assertSee('Base charge: ₱50.00');
    [$xpath, $chart] = rateHistoryChart($response->getContent());
    $months = $xpath->query('.//dl[@aria-label="Monthly unit rates"]/div', $chart);

    expect(array_map(fn ($month) => $xpath->query('./dt', $month)->item(0)->textContent, iterator_to_array($months)))
        ->toBe(['Dec 2026', 'Jan 2027', 'Feb 2027']);
    expect(array_map(fn ($month) => $xpath->query('./dd', $month)->item(0)->textContent, iterator_to_array($months)))
        ->toBe(['₱10.00', 'No rate record', 'No rate record']);
    expect($xpath->query('.//*[@role="listitem"]/div[@aria-hidden="true"]', $chart))->toHaveCount(1);
});

test('billing rate history keeps a real zero rate distinct from missing months', function () {
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
    WaterRate::factory()->create(['water_provider_id' => $provider->id, 'unit_rate' => '0.00']);

    $this->actingAs($staff)->get(route('provider.billing.index'))->assertOk()
        ->assertSee('₱0.00')->assertSee('height: 0%', false)->assertSee('No rate record');
});

test('billing rate history leaves gaps between recorded months blank', function () {
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
    foreach ([1 => '10.00', 3 => '30.00'] as $month => $unitRate) {
        WaterRate::factory()->create([
            'water_provider_id' => $provider->id,
            'effective_from' => sprintf('2026-%02d-01', $month),
            'unit_rate' => $unitRate,
        ]);
    }

    $response = $this->actingAs($staff)->get(route('provider.billing.index'))->assertOk();
    [$xpath, $chart] = rateHistoryChart($response->getContent());
    $slots = $xpath->query('.//*[@role="listitem"]', $chart);

    expect($slots)->toHaveCount(3);
    expect($slots->item(1)->getAttribute('aria-label'))->toBe('February 2026 — No rate record');
    expect($xpath->query('./div', $slots->item(1)))->toHaveCount(0);
    expect($xpath->query('.//dl/div[2]/dd', $chart)->item(0)->textContent)->toBe('No rate record');
});

test('billing rate history selects the latest monthly record within six calendar months for this provider', function () {
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
    foreach (['2026-01-31' => '999.00', '2026-02-01' => '12.00', '2026-04-01' => '24.00', '2026-07-20' => '34.00'] as $date => $unitRate) {
        WaterRate::factory()->create(['water_provider_id' => $provider->id, 'effective_from' => $date, 'unit_rate' => $unitRate]);
    }
    foreach (range(1, 8) as $day) {
        WaterRate::factory()->create(['water_provider_id' => $provider->id, 'effective_from' => sprintf('2026-07-%02d', $day), 'unit_rate' => '88.00']);
    }
    WaterRate::factory()->create(['water_provider_id' => $provider->id, 'effective_from' => '2026-07-21', 'unit_rate' => '35.00']);
    WaterRate::factory()->create(['effective_from' => '2026-08-01', 'unit_rate' => '777.00']);

    $response = $this->actingAs($staff)->get(route('provider.billing.index'))->assertOk();
    [$xpath, $chart] = rateHistoryChart($response->getContent());
    $months = $xpath->query('.//dl[@aria-label="Monthly unit rates"]/div', $chart);

    expect(array_map(fn ($month) => $xpath->query('./dt', $month)->item(0)->textContent, iterator_to_array($months)))
        ->toBe(['Feb 2026', 'Mar 2026', 'Apr 2026', 'May 2026', 'Jun 2026', 'Jul 2026']);
    expect(array_map(fn ($month) => $xpath->query('./dd', $month)->item(0)->textContent, iterator_to_array($months)))
        ->toBe(['₱12.00', 'No rate record', '₱24.00', 'No rate record', 'No rate record', '₱35.00']);
    expect($chart->textContent)->not->toContain('999.00')->not->toContain('777.00')->not->toContain('88.00')->not->toContain('34.00');
});

test('billing rate history pairs every value with its month in a mobile summary', function () {
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
    foreach (range(1, 6) as $month) {
        WaterRate::factory()->create([
            'water_provider_id' => $provider->id,
            'effective_from' => sprintf('2026-%02d-01', $month),
            'unit_rate' => '123.45',
        ]);
    }

    $response = $this->actingAs($staff)->get(route('provider.billing.index'))->assertOk();
    [$xpath, $chart] = rateHistoryChart($response->getContent());
    $values = $xpath->query('.//dl[@aria-label="Monthly unit rates"]/div', $chart);

    expect($values)->toHaveCount(6);
    foreach ($values as $index => $value) {
        expect($xpath->query('./dt', $value)->item(0)->textContent)->toBe(date('M Y', strtotime(sprintf('2026-%02d-01', $index + 1))));
        expect($xpath->query('./dd', $value)->item(0)->textContent)->toBe('₱123.45');
    }
});
