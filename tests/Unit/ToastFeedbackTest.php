<?php

use Tests\TestCase;

pest()->extend(TestCase::class);

beforeEach(function () {
    $this->withViewErrors([]);
});

test('session feedback sends success and all validation errors to toasts once', function () {
    session()->flash('status', 'Consumer saved.');
    $this->withViewErrors(['name' => 'Enter the registered name.', 'email' => 'Enter a valid email.']);

    $view = $this->blade('<x-session-feedback /><x-session-feedback />');
    $document = new DOMDocument;
    @$document->loadHTML((string) $view);
    $fields = new DOMXPath($document);

    $view->assertSee('Consumer saved.')->assertSee('Enter the registered name. Enter a valid email.');
    expect($fields->query('//*[@x-init]'))->toHaveCount(2);
    expect($fields->query('//*[@data-form-feedback]'))->toHaveCount(1);
    expect($fields->query('//*[@data-form-feedback]')->item(0)->getAttribute('x-data'))
        ->toContain('danger')->toContain('duration\\u0022:0');
    expect($fields->query('//ui-callout'))->toHaveCount(0);
});

test('session feedback renders nothing when there are no messages', function () {
    $view = $this->blade('<x-session-feedback />');

    expect(trim((string) $view))->toBe('');
});

test('verification status is shown as a readable toast message', function () {
    $view = $this->blade('<x-auth-session-status status="verification-link-sent" />');

    $view->assertSee('A new verification link has been sent to your email address.')
        ->assertDontSee('verification-link-sent');
});

test('warning toasts retain their action link and escape message content', function () {
    $view = $this->blade('<x-feedback-toast :text="$text" variant="warning" :duration="0" :link="$link" />', [
        'text' => '<script>alert("Overdue")</script>',
        'link' => ['label' => 'Review your bills', 'href' => '/portal/bills'],
    ]);
    $document = new DOMDocument;
    @$document->loadHTML((string) $view);
    $fields = new DOMXPath($document);

    $view->assertSee('Review your bills')->assertDontSee('<script>', false);
    expect($fields->query('//*[@x-init]')->item(0)->getAttribute('x-data'))
        ->toContain('warning')->toContain('duration\\u0022:0')->toContain('\\\\u003Cscript\\\\u003E');
    expect($fields->query('//script'))->toHaveCount(0);
});
