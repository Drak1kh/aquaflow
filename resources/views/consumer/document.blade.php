<x-print-document :title="$documentTitle" :reference="$payment ? $payment->reference : $bill->number">
    <x-slot:issuer>
        <strong>{{ $bill->waterProvider->name }}</strong>
        @if ($bill->waterProvider->address)<p>{{ $bill->waterProvider->address }}</p>@endif
        @if ($bill->waterProvider->phone || $bill->waterProvider->email)<p>{{ implode(' · ', array_filter([$bill->waterProvider->phone, $bill->waterProvider->email])) }}</p>@endif
    </x-slot:issuer>

    @php($paymentStatus = $payment ? $bill->paymentStatus() : ($bill->status === 'void' ? 'Void' : 'Unpaid'))
    @php($statusClass = match ($paymentStatus) { 'Paid' => 'document-status-paid', 'Partially Paid' => 'document-status-partial', 'Unpaid' => 'document-status-unpaid', 'Overdue' => 'document-status-overdue', default => 'document-status-void' })
    <div class="document-columns">
        <section>
            <p class="document-label">{{ __('Consumer') }}</p>
            <p class="document-name">{{ $bill->consumer->name }}</p>
            <p>{{ $bill->consumer->account_number }}</p>
            <p class="document-muted">{{ $bill->consumer->address }}</p>
        </section>
        <dl class="document-facts">
            <div><dt>{{ __('Bill number') }}</dt><dd>{{ $bill->number }}</dd></div>
            <div><dt>{{ __('Billing month') }}</dt><dd>{{ $bill->period_month->format('F Y') }}</dd></div>
            <div><dt>{{ __('Due date') }}</dt><dd class="document-due-date">{{ $bill->due_at->format(app(\App\Actions\PlatformSettings::class)->get('date_format')) }}</dd></div>
            <div><dt>{{ $payment ? __('Bill status') : __('Original bill status') }}</dt><dd class="document-status {{ $statusClass }}">{{ $paymentStatus }}</dd></div>
        </dl>
    </div>

    @if ($payment)
        <section class="document-summary" aria-label="{{ __('Payment received') }}">
            <div><p>{{ __('Payment received — verified') }}</p><strong>₱{{ $payment->amount }}</strong></div>
            <dl class="document-facts">
                <div><dt>{{ __('Payment date') }}</dt><dd>{{ $payment->paid_at->format(app(\App\Actions\PlatformSettings::class)->get('date_format')) }}</dd></div>
                <div><dt>{{ __('Method') }}</dt><dd>{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</dd></div>
                <div><dt>{{ __('Verification') }}</dt><dd>{{ __('Verified') }}</dd></div>
            </dl>
        </section>
        <h2>{{ __('Related bill charges') }}</h2>
    @else
        <section class="document-summary" aria-label="{{ __('Original bill amount') }}">
            <div><p>{{ __('Amount due') }}</p><strong>₱{{ $bill->total }}</strong></div>
            <div><p>{{ __('Payment due') }}</p><span class="document-due-date">{{ $bill->due_at->format(app(\App\Actions\PlatformSettings::class)->get('date_format')) }}</span></div>
        </section>
        <h2>{{ __('Charges and meter readings') }}</h2>
    @endif

    <div class="document-table-wrap">
        <table>
            <thead><tr><th scope="col">{{ __('Description') }}</th><th scope="col" class="amount">{{ __('Value / amount') }}</th></tr></thead>
            <tbody>
                <tr><td>{{ __('Previous reading') }}</td><td class="amount">{{ $bill->previous_reading }} m³</td></tr>
                <tr><td>{{ __('Current reading') }}</td><td class="amount">{{ $bill->current_reading }} m³</td></tr>
                <tr><td>{{ __('Consumption') }}</td><td class="amount">{{ $bill->consumption }} m³</td></tr>
                <tr><td>{{ __('Base charge') }}</td><td class="amount">₱{{ $bill->base_charge }}</td></tr>
                <tr><td>{{ __('Rate per m³') }}</td><td class="amount">₱{{ $bill->unit_rate }}</td></tr>
                <tr><td>{{ __('Consumption charge') }}</td><td class="amount">₱{{ $bill->consumption_charge }}</td></tr>
                <tr><td>{{ __('Penalty') }}</td><td class="amount">₱{{ $bill->late_fee }}</td></tr>
                @foreach ($bill->adjustments as $adjustment)
                    <tr><td>{{ ucfirst($adjustment->type) }} {{ __('adjustment') }} · {{ $adjustment->reason }}</td><td class="amount">₱{{ $adjustment->amount }}</td></tr>
                @endforeach
                <tr class="document-total"><th scope="row">{{ __('Total billed') }}</th><td class="amount">₱{{ $bill->total }}</td></tr>
                <tr><th scope="row">{{ $payment ? __('Remaining balance') : __('Amount due') }}</th><td class="amount">₱{{ $payment ? $bill->balance() : $bill->total }}</td></tr>
            </tbody>
        </table>
    </div>
    <p class="document-note">{{ $payment ? __('This receipt records a verified payment. The remaining balance reflects the current bill balance.') : __('This original bill shows charges before payments. See payment receipts for verified payments and the current balance.') }}</p>
</x-print-document>
