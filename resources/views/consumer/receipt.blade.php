@php($documentTitle = bccomp($receipt['remaining_balance'], '0.00', 2) === 0 ? __('Paid receipt') : __('Payment received'))
<x-print-document :title="$documentTitle" :reference="$receipt['reference']" :generated-date="$receipt['verified_date']">
    <x-slot:issuer>
        <strong>{{ $receipt['issuer']['name'] }}</strong>
        @if ($receipt['issuer']['address'])<p>{{ $receipt['issuer']['address'] }}</p>@endif
        @if ($receipt['issuer']['phone'] || $receipt['issuer']['email'])<p>{{ implode(' · ', array_filter([$receipt['issuer']['phone'], $receipt['issuer']['email']])) }}</p>@endif
    </x-slot:issuer>

    <div class="document-columns">
        <section>
            <p class="document-label">{{ __('Consumer') }}</p>
            <p class="document-name">{{ $receipt['consumer']['name'] }}</p>
            <p>{{ $receipt['consumer']['account_number'] }}</p>
            <p class="document-muted">{{ $receipt['consumer']['address'] }}</p>
        </section>
        <dl class="document-facts">
            <div><dt>{{ __('Payment date') }}</dt><dd>{{ $receipt['payment_date'] }}</dd></div>
            <div><dt>{{ __('Method') }}</dt><dd>{{ $receipt['method'] }}</dd></div>
            <div><dt>{{ __('Verified on') }}</dt><dd>{{ $receipt['verified_date'] }}</dd></div>
        </dl>
    </div>
    <section class="document-summary" aria-label="{{ __('Payment received') }}">
        <div><p>{{ __('Payment received — verified') }}</p><strong>₱{{ $receipt['amount'] }}</strong></div>
        <div><p>{{ __('Total charges on included bills') }}</p><strong>₱{{ $receipt['total_charges'] }}</strong></div>
    </section>

    @foreach ($receipt['bills'] as $bill)
        @php($statusClass = match ($bill['status']) { 'Paid' => 'document-status-paid', 'Partially Paid' => 'document-status-partial', 'Overdue' => 'document-status-overdue', default => 'document-status-unpaid' })
        <h2>{{ $bill['number'] }} · {{ $bill['month'] }}</h2>
        <dl class="document-facts">
            <div><dt>{{ __('Meter') }}</dt><dd>{{ $bill['meter_number'] }} · {{ $bill['connection_number'] }}</dd></div>
            <div><dt>{{ __('Due date') }}</dt><dd class="document-due-date">{{ $bill['due_date'] }}</dd></div>
            <div><dt>{{ __('Bill status') }}</dt><dd class="document-status {{ $statusClass }}">{{ $bill['status'] }}</dd></div>
        </dl>
        <div class="document-table-wrap">
            <table>
                <thead><tr><th scope="col">{{ __('Description') }}</th><th scope="col" class="amount">{{ __('Value / amount') }}</th></tr></thead>
                <tbody>
                    <tr><td>{{ __('Previous reading') }}</td><td class="amount">{{ $bill['previous_reading'] }} m³</td></tr>
                    <tr><td>{{ __('Current reading') }}</td><td class="amount">{{ $bill['current_reading'] }} m³</td></tr>
                    <tr><td>{{ __('Consumption') }}</td><td class="amount">{{ $bill['consumption'] }} m³</td></tr>
                    <tr><td>{{ __('Base charge') }}</td><td class="amount">₱{{ $bill['base_charge'] }}</td></tr>
                    <tr><td>{{ __('Rate per m³') }}</td><td class="amount">₱{{ $bill['unit_rate'] }}</td></tr>
                    <tr><td>{{ __('Consumption charge') }}</td><td class="amount">₱{{ $bill['consumption_charge'] }}</td></tr>
                    <tr><td>{{ __('Penalty') }}</td><td class="amount">₱{{ $bill['late_fee'] }}</td></tr>
                    @foreach ($bill['adjustments'] as $adjustment)
                        <tr><td>{{ $adjustment['description'] }}</td><td class="amount">₱{{ $adjustment['amount'] }}</td></tr>
                    @endforeach
                    <tr class="document-total"><th scope="row">{{ __('Total billed') }}</th><td class="amount">₱{{ $bill['total'] }}</td></tr>
                    <tr><th scope="row">{{ __('Balance before payment') }}</th><td class="amount">₱{{ $bill['balance_before'] }}</td></tr>
                    <tr><th scope="row">{{ __('Applied from this payment') }}</th><td class="amount">₱{{ $bill['applied'] }}</td></tr>
                    <tr><th scope="row">{{ __('Remaining balance') }}</th><td class="amount">₱{{ $bill['balance_after'] }}</td></tr>
                </tbody>
            </table>
        </div>
    @endforeach
    <p class="document-note">{{ __('Charges and balances are fixed at verification. Applied amounts show how this payment was distributed; total billed includes the full charges on each included bill.') }}</p>
</x-print-document>
