<?php

use App\Models\Consumer;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;

test('the public guide contains every role flow and example without loading account records', function () {
    DB::enableQueryLog();

    $response = $this->get(route('docs'));

    $response->assertOk()->assertSee('Consumer guide')->assertSee('Water Provider guide')->assertSee('Super Admin guide')
        ->assertSee('Everyday help for everyone')->assertSee('Plain-language word guide')
        ->assertSee('Fictional example')->assertSee('noindex, nofollow');
    expect(DB::getQueryLog())->toBe([]);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $elements = new DOMXPath($document);
    expect($elements->query('//figure[contains(@class,"docs-flow")]'))->toHaveCount(5);
    expect($elements->query('//aside[contains(@class,"docs-example")]'))->toHaveCount(9);
    $ids = [];
    foreach ($elements->query('//*[@id]') as $element) {
        $ids[] = $element->getAttribute('id');
    }
    expect(count($ids))->toBe(count(array_unique($ids)));
    foreach ($elements->query('//a[@data-doc-topic-link]') as $link) {
        expect($ids)->toContain($link->getAttribute('data-doc-topic-link'));
    }
});

test('every role can read docs without a provider context or completed account setup', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role, 'is_active' => false, 'must_change_password' => true, 'consumer_id' => null, 'water_provider_id' => null]);

    $this->actingAs($user)->get(route('docs'))->assertOk()->assertSee('User guide');

    $this->assertAuthenticatedAs($user);
})->with(UserRole::cases());

test('the guide does not expose a signed in consumers real account data', function () {
    $consumer = Consumer::factory()->create(['name' => 'PRIVATE CONSUMER NAME', 'account_number' => 'PRIVATE-ACCOUNT-0001']);
    $user = User::factory()->create(['consumer_id' => $consumer->id]);

    $this->actingAs($user)->get(route('docs'))->assertOk()
        ->assertDontSee('PRIVATE CONSUMER NAME')->assertDontSee('PRIVATE-ACCOUNT-0001');
});

test('role links are shareable and unsupported role values fall back safely', function () {
    $this->get(route('docs', ['role' => 'provider']))->assertOk()->assertSee('data-initial-role="provider"', false);
    $this->get(route('docs', ['role' => '<script>alert(1)</script>']))->assertOk()
        ->assertSee('data-initial-role="consumer"', false)->assertDontSee('<script>alert(1)</script>', false);
    $this->get(route('docs', ['role' => ['admin']]))->assertOk()->assertSee('data-initial-role="consumer"', false);
});

test('the guide is not linked from the landing page or application navigation', function () {
    $this->get(route('home'))->assertOk()->assertDontSee('href="'.route('docs'), false);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get(route('dashboard.admin'))->assertOk()->assertDontSee('href="'.route('docs'), false);
});
