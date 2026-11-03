<?php

use Symfony\Component\Process\Process;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('modal initialization opens only requested dialogs through Flux and focuses the first field', function (bool $show) {
    $view = $this->blade('<x-accessible-modal name="edit-consumer-4" :show="$show"><input name="name" /></x-accessible-modal>', ['show' => $show]);
    $document = new DOMDocument;
    @$document->loadHTML((string) $view);
    $elements = new DOMXPath($document);
    $wrapper = $elements->query('//div[@x-init]')->item(0);
    $script = <<<'JS'
        const vm = require('node:vm');
        const state = vm.runInNewContext('(' + process.argv[1] + ')');
        const opened = [];
        let focused = false;
        const dialog = { open: false, querySelector: () => ({ focus() { focused = true; } }) };
        vm.runInNewContext(process.argv[2], {
            ...state,
            $el: { querySelector: () => dialog },
            $nextTick: callback => callback(),
            MutationObserver: class { observe() {} },
            $flux: { modal: name => ({ show() { opened.push(name); dialog.open = true; } }) },
        });
        console.log(JSON.stringify({ opened, focused }));
        JS;
    $process = new Process(['node', '-e', $script, $wrapper->getAttribute('x-data'), $wrapper->getAttribute('x-init')]);

    $process->mustRun();

    expect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['opened' => $show ? ['edit-consumer-4'] : [], 'focused' => $show]);
})->with([true, false]);
