<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    @php($platformName = auth()->check() ? \App\Models\SystemSetting::setting('platform_name', config('app.name', 'AquaFlow')) : config('app.name', 'AquaFlow'))
    {{ filled($title ?? null) ? $title.' - '.$platformName : $platformName }}
</title>

<link rel="icon" href="{{ asset('assets/aquaflow-logo/favicon.ico') }}" sizes="any">
<link rel="icon" href="{{ asset('assets/aquaflow-logo/favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('assets/aquaflow-logo/apple-touch-icon.png') }}">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@unless ($forceLightAppearance ?? false)
    @fluxAppearance
@endunless
