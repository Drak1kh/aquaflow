@props(['title', 'description', 'data', 'series', 'unit' => 'PHP'])

@php
    $maximum = 0;
    foreach ($data as $row) {
        foreach ($series as $field => $label) {
            $maximum = max($maximum, (float) $row[$field]);
        }
    }
    $scale = max(1, $maximum);
    $precision = $unit === 'PHP' ? 2 : 3;
@endphp

<flux:card {{ $attributes->class('min-w-0 space-y-5') }}>
    <div class="space-y-2">
        <flux:heading level="2" size="lg">{{ $title }}</flux:heading>
        <flux:text>{{ $description }}</flux:text>
        <flux:text class="text-sm">{{ $data[0]['month'] }} – {{ $data[count($data) - 1]['month'] }} · {{ $unit }}</flux:text>
    </div>

    <ul class="flex flex-wrap gap-x-5 gap-y-2 text-sm" aria-label="{{ __('Chart legend') }}">
        @foreach ($series as $field => $label)
            <li class="flex items-center gap-2">
                <span @class(['w-6 border-t-2', 'border-brand-aqua' => $loop->first, 'border-dashed border-brand-ocean dark:border-brand-mist' => !$loop->first]) aria-hidden="true"></span>
                {{ $label }}
            </li>
        @endforeach
    </ul>

    @if ($maximum > 0)
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="{{ $title }}">
            <svg viewBox="0 0 640 260" class="h-auto w-full min-w-[26rem]" role="img" aria-label="{{ $title }}">
                <title>{{ $title }} — {{ __('Monthly values are available in the data table below.') }}</title>
                @foreach ([0, 0.5, 1] as $fraction)
                    @php $y = 212 - $fraction * 184; @endphp
                    <line x1="76" x2="614" y1="{{ $y }}" y2="{{ $y }}" stroke="currentColor" class="text-zinc-200 dark:text-zinc-700" />
                    <text x="66" y="{{ $y + 5 }}" text-anchor="end" fill="currentColor" class="fill-current text-sm text-zinc-600 dark:text-zinc-300">{{ number_format($scale * $fraction, $scale < 10 ? 1 : 0) }}</text>
                @endforeach

                @foreach ($series as $field => $label)
                    @php
                        $points = [];
                        foreach ($data as $index => $row) {
                            $points[] = (76 + $index * 538 / (count($data) - 1)).','.(212 - (float) $row[$field] / $scale * 184);
                        }
                    @endphp
                    <g @class(['text-brand-aqua' => $loop->first, 'text-brand-ocean dark:text-brand-mist' => !$loop->first])>
                        <polyline points="{{ implode(' ', $points) }}" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="{{ $loop->first ? 'none' : '7 5' }}" stroke-linejoin="round" />
                        @foreach ($data as $index => $row)
                            <circle cx="{{ 76 + $index * 538 / (count($data) - 1) }}" cy="{{ 212 - (float) $row[$field] / $scale * 184 }}" r="4" fill="currentColor">
                                <title>{{ $row['month'] }} · {{ $label }}: {{ number_format((float) $row[$field], $precision) }} {{ $unit }}</title>
                            </circle>
                        @endforeach
                    </g>
                @endforeach

                @foreach ($data as $index => $row)
                    <text x="{{ 76 + $index * 538 / (count($data) - 1) }}" y="246" text-anchor="middle" fill="currentColor" class="fill-current text-sm text-zinc-600 dark:text-zinc-300">{{ substr($row['month'], 0, 3) }}</text>
                @endforeach
            </svg>
        </div>
    @else
        <div class="flex min-h-48 items-center justify-center text-center">
            <flux:text>{{ __('No activity recorded in this period.') }}</flux:text>
        </div>
    @endif

    <details class="group">
        <summary class="cursor-pointer rounded-sm text-sm font-medium text-brand-ink underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-aqua dark:text-brand-mist">{{ __('View monthly values') }}</summary>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm tabular-nums">
                <caption class="sr-only">{{ $title }} · {{ $unit }}</caption>
                <thead><tr class="border-b border-zinc-200 dark:border-zinc-700"><th scope="col" class="py-2 pe-4">{{ __('Month') }}</th>@foreach ($series as $label)<th scope="col" class="px-2 py-2 text-right">{{ $label }} ({{ $unit }})</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($data as $row)
                        <tr class="border-b border-zinc-100 dark:border-zinc-800"><th scope="row" class="py-2 pe-4 font-normal">{{ $row['month'] }}</th>@foreach ($series as $field => $label)<td class="px-2 py-2 text-right">{{ number_format((float) $row[$field], $precision) }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</flux:card>
