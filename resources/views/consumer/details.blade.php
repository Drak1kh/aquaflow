<x-layouts::app>
    <div class="mx-auto max-w-5xl space-y-6">
        <x-page-header :title="'My '.ucfirst($section)" :description="$consumer->account_number" />
        @if ($errors->any())<flux:callout variant="danger">{{ $errors->first() }}</flux:callout>@endif
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if ($section === 'account')
            <flux:card>
                <flux:heading>{{ $consumer->name }}</flux:heading>
                <flux:text>{{ $consumer->phone }} · {{ $consumer->email }}</flux:text>
                <flux:text>{{ $consumer->address }}</flux:text>
                <flux:text>{{ $connections->contains('status', 'active') ? 'Active' : 'Disconnected' }}</flux:text>
            </flux:card>
        @elseif ($section === 'connection')
            @forelse ($connections as $connection)
                <flux:heading>Meter {{ $connection->meter_number }} · {{ ucfirst($connection->status) }}</flux:heading>
                @forelse ($connection->readings as $reading)
                    <flux:card>{{ $reading->period_month->format('M Y') }} · Previous {{ $reading->previous_reading }} · Current {{ $reading->current_reading }} · {{ bcsub($reading->current_reading, $reading->previous_reading, 3) }} m³</flux:card>
                @empty
                    <flux:text>No readings yet.</flux:text>
                @endforelse
            @empty
                <flux:text>No connection assigned yet.</flux:text>
            @endforelse
        @elseif ($section === 'bills')
            @forelse ($bills as $bill)
                <flux:card><a href="{{ route('bills.print', $bill) }}">{{ $bill->number }} · {{ $bill->period_month->format('M Y') }} · View / print</a><flux:text>{{ $bill->consumption }} m³ · ₱{{ $bill->total }} · {{ $bill->paymentStatus() }} · Due {{ $bill->due_at->format('M d, Y') }} · ₱{{ $bill->balance() }} remaining</flux:text></flux:card>
            @empty
                <flux:text>No bills yet.</flux:text>
            @endforelse
        @else
            @foreach ($payments as $payment)
                <flux:card>{{ $payment->reference }} · ₱{{ $payment->amount }} · {{ ucfirst($payment->status) }}
                    @if ($payment->status === 'rejected')<flux:text>Reason: {{ $payment->remarks }}</flux:text>@endif
                    @if ($payment->status === 'verified')<a href="{{ route('payments.print', $payment) }}">Print receipt</a>@endif
                </flux:card>
            @endforeach
            <form method="POST" action="{{ route('portal.payments.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <flux:heading>Submit payment for verification</flux:heading>
                <flux:select name="bill_id" label="Bill" required>
                    @foreach ($bills as $bill)
                        @if ($bill->status !== 'void' && bccomp($bill->balance(), '0', 2) > 0)<option value="{{ $bill->id }}">{{ $bill->number }} · ₱{{ $bill->balance() }}</option>@endif
                    @endforeach
                </flux:select>
                <flux:input name="reference" label="Payment reference" required />
                <flux:input name="amount" label="Amount (PHP)" type="number" step="0.01" min="0.01" required />
                <flux:input name="paid_at" label="Payment date" type="date" required />
                <flux:input name="proof" label="Proof (PDF, JPG or PNG, up to 5 MB)" type="file" required />
                <flux:button type="submit" variant="primary">Submit payment</flux:button>
            </form>
        @endif
    </div>
</x-layouts::app>
