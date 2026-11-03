@props(['title', 'reference' => null, 'issuer' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}{{ $reference ? ' · '.$reference : '' }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f4f5; color: #18181b; font: 14px/1.55 Arial, sans-serif; }
        ::selection { background: #e4e4e7; color: #18181b; }
        .document { max-width: 960px; margin: 28px auto; padding: 40px; background: #fff; border: 1px solid #d4d4d8; }
        .document-header { display: flex; justify-content: space-between; align-items: center; gap: 24px; padding-bottom: 24px; border-bottom: 2px solid #27272a; }
        .document-logo { display: block; width: 200px; max-width: 100%; height: auto; }
        .document-issuer { max-width: 340px; text-align: right; overflow-wrap: anywhere; }
        .document-issuer strong { display: block; font-size: 16px; }
        .document-issuer p { margin: 3px 0 0; color: #52525b; font-size: 12px; }
        .document-title { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 8px 24px; margin: 28px 0; }
        h1 { margin: 0; font-size: 26px; line-height: 1.25; font-weight: 700; letter-spacing: -.02em; }
        h2 { margin: 0 0 12px; font-size: 15px; }
        p { margin: 0; }
        .document-reference { font-size: 13px; color: #52525b; overflow-wrap: anywhere; }
        .document-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 24px 40px; margin-bottom: 28px; }
        .document-label { color: #52525b; font-size: 12px; margin-bottom: 4px; }
        .document-name { font-weight: 700; font-size: 16px; }
        .document-muted { color: #52525b; }
        dl { margin: 0; }
        .document-facts > div { display: flex; justify-content: space-between; gap: 20px; padding: 4px 0; }
        dt { color: #52525b; }
        dd { margin: 0; text-align: right; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
        .document-table-wrap { overflow-x: auto; }
        .document-report { min-width: 700px; }
        .document-scroll-hint { display: none; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 11px 12px; border-bottom: 1px solid #e4e4e7; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
        thead { background: #f4f4f5; }
        th { font-weight: 600; }
        .amount { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .document-total th, .document-total td { border-top: 2px solid #27272a; font-weight: 700; font-size: 16px; }
        .document-summary { display: flex; justify-content: space-between; gap: 20px; align-items: center; margin: 24px 0; padding: 18px 20px; background: #f4f4f5; border: 1px solid #d4d4d8; }
        .document-summary strong { display: block; font-size: 26px; font-variant-numeric: tabular-nums; }
        .document-summary p { color: #52525b; font-size: 12px; }
        .document-note { margin-top: 20px; color: #52525b; font-size: 12px; }
        .document-footer { margin-top: 32px; padding-top: 16px; border-top: 1px solid #d4d4d8; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; color: #52525b; font-size: 11px; }
        .document-toolbar { max-width: 960px; margin: 20px auto 0; padding: 0 16px; display: flex; justify-content: flex-end; }
        button { border: 1px solid #a1a1aa; border-radius: 6px; background: #fff; color: #18181b; padding: 10px 16px; font: inherit; cursor: pointer; }
        button:hover { background: #e4e4e7; }
        button:focus-visible { outline: 2px solid #27272a; outline-offset: 3px; }
        body.embedded { background: #fff; }
        .embedded .document { margin: 0 auto; border: 0; }
        @media (max-width: 600px) {
            .document { margin: 0; padding: 24px 18px; border: 0; }
            .document-header { align-items: flex-start; flex-direction: column; gap: 16px; }
            .document-issuer { text-align: left; }
            .document-logo { width: 170px; }
            .document-columns { grid-template-columns: 1fr; gap: 20px; }
            th, td { padding: 9px 7px; }
            .document-summary { align-items: flex-start; flex-direction: column; padding: 16px; }
            .document-scroll-hint { display: block; margin-bottom: 8px; font-size: 12px; color: #52525b; }
        }
        @page { size: A4; margin: 14mm; }
        @media print {
            body, body.embedded { background: #fff; font-size: 11pt; }
            .document, .embedded .document { max-width: none; margin: 0; padding: 0; border: 0; }
            .document-toolbar { display: none; }
            .document-header, .document-columns, .document-summary, tr { break-inside: avoid; }
            thead { display: table-header-group; }
            th, td { padding: 8px; }
            .document-table-wrap { overflow: visible; }
            .document-report { min-width: 0; }
            .document-scroll-hint { display: none; }
            .document-footer { break-inside: avoid; }
        }
    </style>
</head>
<body @class(['embedded' => request()->boolean('preview')]) data-print-document>
    @unless (request()->boolean('preview'))
        <div class="document-toolbar"><button type="button" onclick="window.print()">{{ __('Print / save PDF') }}</button></div>
    @endunless
    <main class="document">
        <header class="document-header">
            <img class="document-logo" src="{{ asset('assets/aquaflow-banner-image.png') }}" alt="AquaFlow" width="200" height="65">
            <div class="document-issuer">{{ $issuer ?? app(\App\Actions\PlatformSettings::class)->get('platform_name') }}</div>
        </header>
        <div class="document-title">
            <h1>{{ $title }}</h1>
            @if ($reference)<p class="document-reference">{{ $reference }}</p>@endif
        </div>
        {{ $slot }}
        <footer class="document-footer">
            <span>{{ __('AquaFlow · Water Management System') }}</span>
            <span>{{ __('Generated :date', ['date' => now()->format(app(\App\Actions\PlatformSettings::class)->get('date_format').' g:i A')]) }}</span>
        </footer>
    </main>
</body>
</html>
