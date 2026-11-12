<?php

use Symfony\Component\Process\Process;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('document preview exposes separate accessible print and save actions', function () {
    $view = (string) $this->blade('<x-document-preview />');

    expect($view)->toContain('aria-label="Print document"')
        ->toContain('aria-label="Save as PDF"');
});

test('document preview intercepts normal clicks and prints only valid loaded documents', function (string $mode, bool $intercepted) {
    $view = $this->blade('<x-document-preview />');
    $document = new DOMDocument;
    @$document->loadHTML((string) $view);
    $elements = new DOMXPath($document);
    $state = $elements->query('//div[@x-data]')->item(0)->getAttribute('x-data');
    $script = <<<'JS'
        const vm = require('node:vm');
        const mode = process.argv[2];
        const attachment = mode.startsWith('attachment-');
        const opened = [];
        let prevented = false, printed = 0;
        const state = vm.runInNewContext('(' + process.argv[1] + ')', {
            URL,
            window: { location: { href: 'http://localhost:8000/provider/billing', origin: 'http://localhost:8000' } },
            $flux: { modal: name => ({ show() { opened.push(name); } }) },
        });
        state.$refs = { documentFrame: {
            get contentDocument() {
                if (mode === 'attachment-pdf') throw new Error('Native PDF viewer');
                return { title: 'Water bill · B-001', querySelector: () => mode === 'invalid-document' || attachment ? null : {} };
            },
            contentWindow: { focus() {}, print() { printed++; } },
        } };
        const event = {
            target: { closest: () => mode === 'ordinary-link' ? null : {
                href: mode === 'external' ? 'https://example.com/bill' : 'http://localhost:8000/documents/bills/1?existing=yes',
                dataset: { documentPreview: attachment ? 'attachment' : '' },
            } },
            button: 0, ctrlKey: mode === 'modified-click', defaultPrevented: false,
            preventDefault() { prevented = true; },
        };
        state.print();
        state.open(event);
        if (prevented) {
            state.loaded();
            if (mode === 'repeat-click') state.open(event);
            state.print();
        }
        console.log(JSON.stringify({ prevented, opened, printed, url: state.url, title: state.title, ready: state.ready }));
        JS;
    $process = new Process(['node', '-e', $script, $state, $mode]);

    $process->mustRun();

    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    expect($result['prevented'])->toBe($intercepted);
    expect($result['opened'])->toBe($mode === 'repeat-click' ? ['document-preview', 'document-preview'] : ($intercepted ? ['document-preview'] : []));
    expect($result['printed'])->toBe(in_array($mode, ['normal', 'repeat-click'], true) ? 1 : 0);
    expect($result['ready'])->toBe(in_array($mode, ['normal', 'repeat-click', 'attachment-image', 'attachment-pdf'], true));
    expect($result['url'])->toBe($intercepted ? 'http://localhost:8000/documents/bills/1?existing=yes&preview=1' : null);
    expect($result['title'])->toBe(in_array($mode, ['normal', 'repeat-click'], true) ? 'Water bill · B-001' : 'Document preview');
})->with([
    ['normal', true],
    ['repeat-click', true],
    ['invalid-document', true],
    ['attachment-image', true],
    ['attachment-pdf', true],
    ['modified-click', false],
    ['external', false],
    ['ordinary-link', false],
]);
