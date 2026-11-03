@php
    $roles = ['consumer' => 'Consumer', 'provider' => 'Water Provider', 'admin' => 'Super Admin'];
    $initialRole = in_array(request('role'), array_keys($roles), true) ? request('role') : 'consumer';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => 'User guide', 'forceLightAppearance' => true])
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Simple AquaFlow instructions for consumers, water providers, and administrators. Learn about water bills, payments, meter readings, and support.">
</head>
<body class="docs-page" data-docs data-initial-role="{{ $initialRole }}">
    <a class="docs-skip" href="#docs-main">Skip to the guide</a>
    <header class="docs-header">
        <div class="docs-header-inner">
            <a href="{{ route('home') }}" aria-label="AquaFlow home"><img src="{{ asset('assets/aquaflow-banner-image.png') }}" alt="AquaFlow" width="180" height="60"></a>
            <span class="docs-header-label">User guide</span>
            <flux:button :href="route('dashboard')" size="sm">Open AquaFlow</flux:button>
        </div>
    </header>

    <x-page-loading>
        <div class="docs-intro">
            <h1>Your guide to AquaFlow</h1>
            <p>Learn how to use AquaFlow, from checking your first water bill to managing a provider. Choose the role you use in the system.</p>
            <nav class="docs-role-picker" aria-label="Choose your role">
                @foreach ($roles as $value => $label)
                    <a href="{{ route('docs', ['role' => $value]).'#'.$value.'-start' }}" data-doc-role-link="{{ $value }}" @if ($initialRole === $value) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <p class="docs-intro-note">Available buttons depend on your permissions. This guide explains the current system; your provider sets its water rates and service rules.</p>
        </div>

        <div class="docs-layout">
            <aside class="docs-sidebar" aria-label="Guide navigation">
                <div class="docs-search">
                    <flux:input type="search" label="Find a topic" placeholder="Try payment" data-doc-search autocomplete="off" />
                    <button type="button" class="docs-clear" data-doc-clear hidden>Clear search</button>
                </div>
                <div class="docs-mobile-topics">
                    <label for="docs-topic-select">Jump to a topic</label>
                    <select id="docs-topic-select" data-doc-topic-select><option value="">Choose a topic</option></select>
                </div>
                <nav class="docs-toc" aria-label="On this page" data-doc-toc>
                    <h2>In this guide</h2>
                    <ul>
                        <li><a href="#consumer-start">Consumer guide</a></li>
                        <li><a href="#provider-start">Water Provider guide</a></li>
                        <li><a href="#admin-start">Super Admin guide</a></li>
                        <li><a href="#shared-sign-in">Everyday help</a></li>
                    </ul>
                </nav>
                <noscript><p class="docs-note">All role guides are shown below. Use the topic links or your browser’s Find tool to locate a question.</p></noscript>
            </aside>

            <main id="docs-main" class="docs-main" tabindex="-1">
                <p class="docs-search-status" role="status" aria-live="polite" data-doc-search-status hidden></p>
                @include('docs.consumer')
                @include('docs.provider')
                @include('docs.admin')
                @include('docs.shared')
            </main>
        </div>
    </x-page-loading>
    <footer class="docs-footer">AquaFlow user guide · Examples use fictional names, account numbers, and amounts.</footer>
    @livewireScripts
    @fluxScripts
</body>
</html>
