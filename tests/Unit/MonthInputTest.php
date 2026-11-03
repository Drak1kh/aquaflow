<?php

use Tests\TestCase;

pest()->extend(TestCase::class);

beforeEach(function () {
    $this->withViewErrors([]);
});

test('month filters offer every month and allow an unfiltered selection', function () {
    $view = $this->blade('<x-month-input name="month" label="Billing month" />');
    $document = new DOMDocument;
    @$document->loadHTML((string) $view);
    $fields = new DOMXPath($document);

    $view->assertSee('Billing month')->assertSee('All months');
    expect($fields->query('//select/option'))->toHaveCount(13);
    expect($fields->query('//select/option[@value="01"]')->item(0)->textContent)->toBe('January');
    expect($fields->query('//select/option[@value="12"]')->item(0)->textContent)->toBe('December');
    expect($fields->query('//select/option[@selected]')->item(0)->getAttribute('value'))->toBe('');
    expect($fields->query('//select[@required]'))->toHaveCount(0);
    expect($fields->query('//input[@name="month"]')->item(0)->getAttribute('value'))->toBe('');
});

test('month fields preserve the selected month year and submitted value', function (string $value, string $month, string $year) {
    $view = $this->blade('<x-month-input name="period_month" :value="$value" required />', ['value' => $value]);
    $document = new DOMDocument;
    @$document->loadHTML((string) $view);
    $fields = new DOMXPath($document);

    expect($fields->query('//select/option[@selected]')->item(0)->getAttribute('value'))->toBe($month);
    expect($fields->query('//input[@type="number"]')->item(0)->getAttribute('value'))->toBe($year);
    expect($fields->query('//input[@name="period_month"]')->item(0)->getAttribute('value'))->toBe($value);
    expect($fields->query('//select[@required]'))->toHaveCount(1);
})->with([
    ['2026-01', '01', '2026'],
    ['2030-12', '12', '2030'],
]);

test('invalid month values leave the picker unselected and display validation errors', function () {
    $this->withViewErrors(['period_month' => 'Choose a valid billing month.']);

    $view = $this->blade('<x-month-input name="period_month" :value="$value" required />', ['value' => '2026-13']);
    $document = new DOMDocument;
    @$document->loadHTML((string) $view);
    $fields = new DOMXPath($document);

    $view->assertSee('Choose a valid billing month.')->assertSee('Select month');
    expect($fields->query('//select/option[@selected]')->item(0)->getAttribute('value'))->toBe('');
    expect($fields->query('//input[@name="period_month"]')->item(0)->getAttribute('value'))->toBe('');
});
