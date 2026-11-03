<?php

use Tests\TestCase;

pest()->extend(TestCase::class);

test('charts show both series and accessible monthly values', function () {
    $view = $this->blade('<x-dashboard-chart title="Billing trend" description="Monthly activity" :data="$data" :series="$series" />', [
        'data' => [
            ['month' => 'Dec 2025', 'billed' => '150.25', 'collected' => '50.00'],
            ['month' => 'Jan 2026', 'billed' => '0.00', 'collected' => '20.00'],
        ],
        'series' => ['billed' => 'Billed', 'collected' => 'Collected'],
    ]);

    $view->assertSee('Billing trend')->assertSee('View monthly values')->assertSee('150.25')->assertSee('50.00');
    $document = new DOMDocument;
    @$document->loadHTML((string) $view);
    $elements = new DOMXPath($document);
    expect($elements->query('//polyline'))->toHaveCount(2);
    expect($elements->query('//circle'))->toHaveCount(4);
    expect($elements->query('//table/tbody/tr'))->toHaveCount(2);
    expect($elements->query('//svg[@role="img"]'))->toHaveCount(1);
});

test('empty charts explain missing activity and retain zero values in the table', function () {
    $view = $this->blade('<x-dashboard-chart title="Water consumption" description="Metered usage" :data="$data" :series="$series" unit="m³" />', [
        'data' => [['month' => 'Jan 2026', 'consumption' => '0.000']],
        'series' => ['consumption' => 'Consumption'],
    ]);

    $view->assertSee('No activity recorded in this period.')->assertSee('0.000')->assertSee('m³')->assertDontSee('<polyline', false);
});
