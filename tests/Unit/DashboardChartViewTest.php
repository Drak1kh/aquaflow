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

test('compact charts omit the monthly values disclosure', function () {
    $view = $this->blade('<x-dashboard-chart compact title="Water consumption" description="Metered usage" :data="$data" :series="$series" unit="m³" />', [
        'data' => [['month' => 'Dec 2025', 'consumption' => '8.000'], ['month' => 'Jan 2026', 'consumption' => '10.000']],
        'series' => ['consumption' => 'Consumption'],
    ]);

    $view->assertSee('Water consumption')->assertDontSee('View monthly values')->assertDontSee('Monthly values are available in the data table below.');
    $document = new DOMDocument;
    @$document->loadHTML((string) $view);
    $elements = new DOMXPath($document);
    expect($elements->query('//table'))->toHaveCount(0);
    expect($elements->query('//svg[@role="img"]')->item(0)->getAttribute('viewbox'))->toBe('0 0 640 90');
});

test('empty charts explain missing activity and retain zero values in the table', function () {
    $view = $this->blade('<x-dashboard-chart title="Water consumption" description="Metered usage" :data="$data" :series="$series" unit="m³" />', [
        'data' => [['month' => 'Jan 2026', 'consumption' => '0.000']],
        'series' => ['consumption' => 'Consumption'],
    ]);

    $view->assertSee('No activity recorded in this period.')->assertSee('0.000')->assertSee('m³')->assertDontSee('<polyline', false);
});

test('grouped bar charts scale each permitted series and omit zero height bars', function () {
    $view = $this->blade('<x-dashboard-chart variant="grouped-bars" title="Billing trend" description="Monthly activity" :data="$data" :series="$series" />', [
        'data' => [
            ['month' => 'Dec 2025', 'billed' => '100.00', 'collected' => '50.00'],
            ['month' => 'Jan 2026', 'billed' => '0.00', 'collected' => '25.00'],
        ],
        'series' => ['billed' => 'Billed', 'collected' => 'Collected'],
    ]);

    $document = new DOMDocument;
    @$document->loadHTML((string) $view);
    $elements = new DOMXPath($document);
    $bars = $elements->query('//svg//rect');

    expect($bars)->toHaveCount(3);
    expect($elements->query('//polyline'))->toHaveCount(0);
    expect($elements->query('//svg/line[@stroke-dasharray]'))->toHaveCount(5);
    expect((float) $bars->item(0)->getAttribute('height'))->toBe(184.0);
    expect((float) $bars->item(1)->getAttribute('height'))->toBe(92.0);
    expect((float) $bars->item(2)->getAttribute('height'))->toBe(46.0);
    expect((float) $bars->item(0)->getAttribute('x') + (float) $bars->item(0)->getAttribute('width'))
        ->toBeLessThan((float) $bars->item(1)->getAttribute('x'));
    expect($elements->query('//svg//g[contains(@class, "text-slate-700")]'))->toHaveCount(1);
    expect($elements->query('//svg//g[contains(@class, "text-cyan-500")]'))->toHaveCount(1);
    expect($elements->query('//table/tbody/tr'))->toHaveCount(2);
    $view->assertSee('Dec 2025 · Billed: 100.00 PHP')->assertSee('Jan 2026 · Collected: 25.00 PHP')->assertSee('View monthly values');
});

test('a single permitted bar series stays centered for one positive month', function () {
    $view = $this->blade('<x-dashboard-chart variant="grouped-bars" title="Billing trend" description="Monthly activity" :data="$data" :series="$series" />', [
        'data' => [['month' => 'Jan 2026', 'billed' => '100.00', 'collected' => '999.00']],
        'series' => ['billed' => 'Billed'],
    ]);

    $document = new DOMDocument;
    @$document->loadHTML((string) $view);
    $elements = new DOMXPath($document);
    $bars = $elements->query('//svg//rect');

    expect($bars)->toHaveCount(1);
    expect((float) $bars->item(0)->getAttribute('x') + (float) $bars->item(0)->getAttribute('width') / 2)->toBe(345.0);
    expect((float) $bars->item(0)->getAttribute('height'))->toBe(184.0);
    $view->assertDontSee('Collected')->assertDontSee('999.00');
});

test('dotted consumption charts keep straight emerald segments and invisible tooltip targets', function () {
    $view = $this->blade('<x-dashboard-chart variant="dotted-line" title="Water consumption" description="Metered usage" :data="$data" :series="$series" unit="m3" />', [
        'data' => [['month' => 'Dec 2025', 'consumption' => '8.000'], ['month' => 'Jan 2026', 'consumption' => '10.000']],
        'series' => ['consumption' => 'Consumption'],
    ]);

    $document = new DOMDocument;
    @$document->loadHTML((string) $view);
    $elements = new DOMXPath($document);

    expect($elements->query('//polyline[@stroke-dasharray="1 6"][@stroke-linecap="round"]'))->toHaveCount(1);
    expect($elements->query('//svg//g[contains(@class, "text-emerald-500")]'))->toHaveCount(1);
    expect($elements->query('//circle[@fill="transparent"]'))->toHaveCount(2);
    expect($elements->query('//circle[not(@fill="transparent")]'))->toHaveCount(0);
    $view->assertSee('Dec 2025 · Consumption: 8.000 m3')->assertSee('View monthly values');
});

test('a single consumption month has a finite visible dotted segment', function () {
    $view = $this->blade('<x-dashboard-chart variant="dotted-line" title="Water consumption" description="Metered usage" :data="$data" :series="$series" unit="m3" />', [
        'data' => [['month' => 'Jan 2026', 'consumption' => '8.000']],
        'series' => ['consumption' => 'Consumption'],
    ]);

    $document = new DOMDocument;
    @$document->loadHTML((string) $view);
    $elements = new DOMXPath($document);
    $points = explode(' ', $elements->query('//polyline')->item(0)->getAttribute('points'));

    expect($points)->toHaveCount(2);
    expect($points[0])->not->toBe($points[1]);
    expect($elements->query('//circle[@cx="345"][@cy="28"][@fill="transparent"]'))->toHaveCount(1);
});

test('styled charts preserve the no activity state and zero value table', function (string $variant) {
    $view = $this->blade('<x-dashboard-chart :variant="$variant" title="Monthly activity" description="Monthly activity" :data="$data" :series="$series" />', [
        'variant' => $variant,
        'data' => [['month' => 'Jan 2026', 'billed' => '0.00']],
        'series' => ['billed' => 'Billed'],
    ]);

    $view->assertSee('No activity recorded in this period.')->assertSee('0.00')->assertSee('View monthly values')->assertDontSee('<svg', false);
})->with(['grouped-bars', 'dotted-line']);
