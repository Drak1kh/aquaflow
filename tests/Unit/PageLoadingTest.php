<?php

use Symfony\Component\Process\Process;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('page content is immediately available while Flux placeholders start hidden', function (string $variant) {
    $view = $this->blade('<x-page-loading :variant="$variant"><h1>Ready content</h1></x-page-loading>', ['variant' => $variant]);
    $document = new DOMDocument;
    @$document->loadHTML((string) $view);
    $elements = new DOMXPath($document);

    $view->assertSee('Ready content')->assertSee('data-flux-skeleton', false);
    expect($elements->query('//*[@data-page-loading and @hidden and @aria-hidden="true"]'))->toHaveCount(1);
    expect($elements->query('//*[@data-page-content and not(@hidden)]'))->toHaveCount(1);
    expect($elements->query('//*[@data-page-shell and @aria-busy="false"]'))->toHaveCount(1);
})->with(['page', 'dashboard', 'form']);

test('page loading follows real navigation and restores content safely', function (string $scenario, bool $shouldLoad) {
    $script = <<<'JS'
        const vm = require('node:vm');
        const fs = require('node:fs');
        const scenario = process.argv[2];
        const handlers = new Map();
        const timers = new Map();
        const microtasks = [];
        let timerId = 0;
        const skeleton = { hidden: true, attributes: {}, setAttribute(name, value) { this.attributes[name] = value; } };
        const content = { hidden: false, inert: false };
        const shell = { attributes: { 'aria-busy': 'false' }, setAttribute(name, value) { this.attributes[name] = value; }, querySelector(selector) { return selector === '[data-page-loading]' ? skeleton : content; } };
        const register = (name, callback) => handlers.set(name, [...(handlers.get(name) || []), callback]);
        const window = {
            location: new URL('http://localhost:8000/provider/consumers'),
            addEventListener: register,
            setTimeout(callback) { const id = ++timerId; timers.set(id, callback); return id; },
            clearTimeout(id) { timers.delete(id); },
        };
        vm.runInNewContext(fs.readFileSync(process.argv[1], 'utf8'), {
            window, URL, queueMicrotask: callback => microtasks.push(callback),
            document: { addEventListener: register, querySelector: () => shell, querySelectorAll: () => [shell] },
        });
        const fire = (name, event = {}) => (handlers.get(name) || []).forEach(callback => callback(event));
        const attrs = scenario === 'document' ? ['data-document-preview'] : scenario === 'livewire-link' ? ['wire:navigate'] : scenario === 'download' ? ['download'] : [];
        const href = scenario === 'external' ? 'https://example.com' : scenario === 'hash' ? 'http://localhost:8000/provider/consumers#section' : scenario === 'proof' ? 'http://localhost:8000/portal/payments/1/proof' : scenario === 'csv' ? 'http://localhost:8000/admin/reports/csv' : 'http://localhost:8000/provider/billing';
        const link = { href, target: '', attributes: attrs.map(name => ({name})), hasAttribute: name => attrs.includes(name) };
        const event = { defaultPrevented: false, button: 0, ctrlKey: scenario === 'modified', target: { closest: () => link } };
        if (scenario === 'form' || scenario === 'livewire-form') {
            event.target = {tagName:'FORM', action:href, method:'post', target:'', attributes: scenario === 'livewire-form' ? [{name:'wire:submit'}] : []};
            fire('submit', event);
        } else if (['native', 'external', 'hash', 'document', 'download', 'proof', 'csv', 'modified', 'livewire-link'].includes(scenario)) {
            fire('click', event);
        } else {
            fire('livewire:navigate', event);
        }
        if (scenario === 'canceled') event.defaultPrevented = true;
        if (scenario === 'cached') fire('livewire:navigated');
        microtasks.splice(0).forEach(callback => callback());
        if (scenario === 'fast') fire('livewire:navigated');
        const beforeDelay = !skeleton.hidden;
        [...timers.values()].forEach(callback => callback());
        const loading = !skeleton.hidden && content.hidden && content.inert && shell.attributes['aria-busy'] === 'true';
        fire(scenario === 'history' ? 'pageshow' : scenario === 'failed' ? 'unhandledrejection' : 'livewire:navigated');
        console.log(JSON.stringify({ beforeDelay, loading, restored: skeleton.hidden && !content.hidden && !content.inert && shell.attributes['aria-busy'] === 'false' }));
        JS;
    $process = new Process(['node', '-e', $script, resource_path('js/page-loading.js'), $scenario]);

    $process->mustRun();

    expect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['beforeDelay' => false, 'loading' => $shouldLoad, 'restored' => true]);
})->with([
    ['navigation', true], ['native', true], ['form', true], ['history', true], ['failed', true],
    ['fast', false], ['cached', false], ['canceled', false], ['external', false], ['hash', false],
    ['document', false], ['download', false], ['proof', false], ['csv', false], ['modified', false],
    ['livewire-link', false], ['livewire-form', false],
]);
