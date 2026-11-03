<x-print-document :title="ucfirst($filters['type']).' '.__('report')">
    <div class="document-columns">
        <dl class="document-facts">
            <div><dt>{{ __('Provider scope') }}</dt><dd>{{ $providerName ?? __('All providers') }}</dd></div>
            <div><dt>{{ __('From') }}</dt><dd>{{ $filters['from'] ?? __('All dates') }}</dd></div>
            <div><dt>{{ __('To') }}</dt><dd>{{ $filters['to'] ?? __('All dates') }}</dd></div>
        </dl>
        <dl class="document-facts">
            <div><dt>{{ __('Report type') }}</dt><dd>{{ ucfirst($filters['type']) }}</dd></div>
            <div><dt>{{ __('Records') }}</dt><dd>{{ $rows->count() }}</dd></div>
        </dl>
    </div>
    <p class="document-scroll-hint">{{ __('Scroll to view all columns.') }}</p>
    <div class="document-table-wrap" tabindex="0" role="region" aria-label="{{ __('Report records') }}">
        <table class="document-report">
            <thead><tr>
                @foreach ($headers as $heading)<th scope="col">{{ $heading }}</th>@endforeach
            </tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>@foreach ($row as $value)<td>{{ $value }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($headers) }}">{{ __('No records match this report.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-print-document>
