<?php

use Symfony\Component\Process\Process;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('documentation search limits results to the chosen role and shared help', function (string $role, string $query, array $expected) {
    $script = <<<'JS'
        const { pathToFileURL } = require('node:url');
        (async () => {
            const { filterDocumentationTopics } = await import(pathToFileURL(process.argv[1]));
            const topics = [
                { id: 'consumer-payments', role: 'consumer', text: 'Submit payment proof for a water bill' },
                { id: 'provider-readings', role: 'provider', text: 'Record meter readings and consumption' },
                { id: 'admin-reports', role: 'admin', text: 'Export a billing report' },
                { id: 'shared-login', role: 'shared', text: 'Reset a password and log in' },
            ];
            console.log(JSON.stringify(filterDocumentationTopics(topics, process.argv[2], process.argv[3]).map(topic => topic.id)));
        })();
        JS;
    $process = new Process(['node', '-e', $script, resource_path('js/docs.js'), $role, $query]);

    $process->mustRun();

    expect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR))->toBe($expected);
})->with([
    ['consumer', '', ['consumer-payments', 'shared-login']],
    ['provider', ' METER  readings ', ['provider-readings']],
    ['admin', 'password', ['shared-login']],
    ['consumer', 'report', []],
    ['consumer', 'payment proof', ['consumer-payments']],
    ['provider', 'payment proof', []],
]);

test('documentation URLs restore the role and topic safely', function (string $query, string $role, string $topic) {
    $script = <<<'JS'
        const { pathToFileURL } = require('node:url');
        (async () => {
            const { documentationLocation } = await import(pathToFileURL(process.argv[1]));
            console.log(JSON.stringify(documentationLocation('http://localhost:8000/docs' + process.argv[2], [
                {id:'provider-readings', role:'provider'}, {id:'shared-login', role:'shared'},
            ])));
        })();
        JS;
    $process = new Process(['node', '-e', $script, resource_path('js/docs.js'), $query]);

    $process->mustRun();

    expect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR))->toBe(['role' => $role, 'topic' => $topic]);
})->with([
    ['', 'consumer', ''],
    ['?role=admin', 'admin', ''],
    ['?role=unknown#missing-topic', 'consumer', ''],
    ['?role=consumer#provider-readings', 'provider', 'provider-readings'],
    ['?role=admin#shared-login', 'admin', 'shared-login'],
]);
