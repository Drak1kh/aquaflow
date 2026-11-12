<?php

use App\Models\User;
use App\Models\WaterProvider;
use App\Models\WaterRate;

test('billing rate history shows a zero based currency scale and an exact value for each unit rate', function () {
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    foreach (['999.00', '0.00', '4.00', '8.00', '12.00', '16.00', '20.00'] as $index => $unitRate) {
        WaterRate::factory()->create([
            'water_provider_id' => $provider->id,
            'effective_from' => sprintf('2026-%02d-01', $index + 1),
            'unit_rate' => $unitRate,
            'base_charge' => '777.00',
        ]);
    }

    $response = $this->actingAs($staff)->get(route('provider.billing.index'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $chart = $xpath->query('//figure[@aria-label="Unit-rate history"]')->item(0);

    expect($chart)->not->toBeNull();
    expect($xpath->query('ancestor::section[1]', $chart)->item(0)->getAttribute('class'))
        ->toBe('grid gap-4 sm:grid-cols-2');
    expect($xpath->query('.//*[@aria-label="Unit rate scale"]', $chart)->item(0)->getAttribute('class'))
        ->toContain('h-16');
    expect($xpath->query('.//*[@role="list"]/..', $chart)->item(0)->getAttribute('class'))
        ->toContain('h-16');
    expect($chart->textContent)->toContain('Unit rate (₱ / m³)')->not->toContain('777.00')->not->toContain('999.00');
    $axis = $xpath->query('.//*[@aria-label="Unit rate scale"]/span', $chart);
    expect(array_map(fn ($tick) => trim($tick->textContent), iterator_to_array($axis)))
        ->toBe(['₱20.00', '₱15.00', '₱10.00', '₱5.00', '₱0.00']);
    $bars = $xpath->query('.//*[@role="listitem"]', $chart);
    expect($bars)->toHaveCount(6);
    foreach (['0.00', '4.00', '8.00', '12.00', '16.00', '20.00'] as $index => $unitRate) {
        $bar = $bars->item($index);
        expect($bar->textContent)->toContain('₱'.$unitRate);
        expect($bar->getAttribute('aria-label'))->toBe(date('F Y', strtotime(sprintf('2026-%02d-01', $index + 2))).' — ₱'.$unitRate.' / m³');
        expect($xpath->query('./div[@aria-hidden="true"]', $bar)->item(0)->getAttribute('style'))
            ->toBe('height: '.($index * 20).'%');
    }
});

test('billing rate history preserves its empty state', function () {
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);

    $this->actingAs($staff)->get(route('provider.billing.index'))->assertOk()->assertSee('No rate records yet.');
});

test('billing rate history handles an all zero unit rate history', function () {
    $provider = WaterProvider::factory()->create();
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $provider->id]);
    WaterRate::factory()->create(['water_provider_id' => $provider->id, 'unit_rate' => '0.00']);

    $this->actingAs($staff)->get(route('provider.billing.index'))->assertOk()
        ->assertSee('₱0.00')->assertSee('height: 0%', false);
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
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $values = $xpath->query('//figure[@aria-label="Unit-rate history"]//dl[@aria-label="Monthly unit rates"]/div');

    expect($values)->toHaveCount(6);
    foreach ($values as $index => $value) {
        expect($xpath->query('./dt', $value)->item(0)->textContent)->toBe(date('M Y', strtotime(sprintf('2026-%02d-01', $index + 1))));
        expect($xpath->query('./dd', $value)->item(0)->textContent)->toBe('₱123.45');
    }
});
