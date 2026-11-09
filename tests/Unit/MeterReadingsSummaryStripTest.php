<?php

use Tests\TestCase;

uses(TestCase::class);

test('meter readings summary uses a compact aligned desktop strip', function () {
    $readings = file_get_contents(base_path('resources/views/provider/readings.blade.php'));

    expect($readings)
        ->toContain('grid w-full gap-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_2.5fr]')
        ->toContain('lg:grid-cols-[1fr_1fr_1fr_2.5fr]')
        ->toContain('min-h-40 flex flex-col justify-center gap-2 text-center')
        ->toContain('<x-dashboard-chart compact class="min-h-40 min-w-0"');
});
