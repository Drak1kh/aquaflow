<x-layouts::app :title="__('My water account')">
    <main class="flex w-full min-w-0 flex-col gap-8 py-8 text-[#062E68] dark:text-white">
        <header><flux:heading size="xl">My water account</flux:heading><flux:text>{{ $consumer->provider->name }} · Account {{ $consumer->account_number }}</flux:text></header>
        @include('partials.aquaflow-alerts')

        @php($latestBill = $bills->first())
        @php($latestConnection = $connections->first())
        <div class="grid gap-6 md:grid-cols-3">
            <flux:card><flux:text>Registered name</flux:text><flux:heading>{{ $consumer->name }}</flux:heading><flux:text>{{ $consumer->service_address }}</flux:text></flux:card>
            <flux:card><flux:text>Current bill</flux:text><flux:heading>{{ $latestBill?->total_amount ?? '—' }}</flux:heading><flux:text>Due {{ $latestBill?->due_on ?? '—' }}</flux:text></flux:card>
            <flux:card><flux:text>Outstanding balance</flux:text><flux:heading>{{ \App\DecimalAmount::format($bills->sum(fn ($bill) => $bill->balanceCents())) }}</flux:heading></flux:card>
            <flux:card><flux:text>Connection status</flux:text><flux:heading>{{ $latestConnection ? str_replace('_', ' ', $latestConnection->status) : 'No connection' }}</flux:heading></flux:card>
            <flux:card><flux:text>Latest consumption</flux:text><flux:heading>{{ $latestBill?->consumption ?? '—' }}</flux:heading></flux:card>
            <flux:card><flux:text>Latest payment</flux:text><flux:heading>{{ $payments->first()?->status ?? 'No payment' }}</flux:heading></flux:card>
        </div>

        <flux:card class="space-y-4"><flux:heading>Contact settings</flux:heading>
            <form method="POST" action="{{ route('consumer.contact.update') }}" class="flex flex-wrap items-end gap-3">@csrf @method('PUT')
                <flux:input name="email" label="Email" type="email" value="{{ auth()->user()->email }}" required />
                <flux:input name="contact_number" label="Contact number" value="{{ $consumer->contact_number }}" />
                <flux:button type="submit">Save contact</flux:button>
            </form>
            <flux:text>Change your password and security options in <a href="{{ route('security.edit') }}" class="text-[#079EE9] underline">Security settings</a>.</flux:text>
        </flux:card>

        <flux:card class="space-y-4"><flux:heading>My connections and consumption</flux:heading>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($connections as $connection)
                    <div class="space-y-2 rounded-xl border border-[#BDECF8] p-4 dark:border-zinc-700">
                        <div class="flex justify-between gap-2"><strong>{{ $connection->connection_number }}</strong><flux:badge>{{ str_replace('_', ' ', $connection->status) }}</flux:badge></div>
                        <p class="text-sm">Meter {{ $connection->meter->meter_number }} · {{ $connection->service_address }}</p>
                        <flux:text>Recent readings</flux:text>
                        <ul class="text-sm">
                            @foreach ($connection->readings->sortByDesc('reading_date')->take(6) as $reading)
                                <li>{{ $reading->reading_date }} · {{ $reading->previous_reading }} → {{ $reading->current_reading }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <flux:card class="space-y-4"><flux:heading>Bills</flux:heading>
            <div class="overflow-x-auto"><flux:table><flux:table.columns><flux:table.column>Bill</flux:table.column><flux:table.column>Period</flux:table.column><flux:table.column>Due</flux:table.column><flux:table.column>Amount</flux:table.column><flux:table.column>Balance</flux:table.column><flux:table.column>Status</flux:table.column></flux:table.columns><flux:table.rows>
                @foreach ($bills as $bill)
                    <flux:table.row><flux:table.cell><a class="text-[#079EE9] underline" href="{{ route('consumer.bills.show', $bill) }}">{{ $bill->bill_number }}</a></flux:table.cell><flux:table.cell>{{ $bill->period->label }}</flux:table.cell><flux:table.cell>{{ $bill->due_on }}</flux:table.cell><flux:table.cell>{{ $bill->total_amount }}</flux:table.cell><flux:table.cell>{{ \App\DecimalAmount::format($bill->balanceCents()) }}</flux:table.cell><flux:table.cell>{{ str_replace('_', ' ', $bill->status) }}</flux:table.cell></flux:table.row>
                @endforeach
            </flux:table.rows></flux:table></div>
        </flux:card>

        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card class="space-y-4"><flux:heading>Submit payment proof</flux:heading>
                <form method="POST" action="{{ route('consumer.payments.submit') }}" enctype="multipart/form-data" class="space-y-4">@csrf
                    <flux:select name="bill_id" label="Bill" required>
                        @foreach ($bills->filter(fn ($bill) => $bill->balanceCents() > 0 && $bill->status !== 'void') as $bill)
                            <flux:select.option value="{{ $bill->id }}">{{ $bill->bill_number }} · Balance {{ \App\DecimalAmount::format($bill->balanceCents()) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input name="amount" label="Amount" type="number" step="0.01" min="0.01" required />
                    <flux:select name="method" label="Method" required><flux:select.option value="bank_transfer">Bank transfer</flux:select.option><flux:select.option value="e_wallet">E-wallet</flux:select.option><flux:select.option value="other">Other</flux:select.option></flux:select>
                    <flux:input name="proof" label="Proof image or PDF" type="file" accept=".jpg,.jpeg,.png,.pdf" required />
                    <flux:button type="submit" variant="primary">Submit for verification</flux:button>
                </form>
            </flux:card>
            <flux:card class="space-y-4"><flux:heading>Payment history</flux:heading>
                <ul class="space-y-2 text-sm">@foreach ($payments as $payment)<li class="rounded border border-[#BDECF8] p-3 dark:border-zinc-700">{{ $payment->reference_number }} · {{ $payment->amount }} · {{ $payment->status }} · {{ $payment->created_at->toDateString() }} @if ($payment->status === 'verified') · <a class="text-[#079EE9] underline" href="{{ route('consumer.payments.receipt', $payment) }}">Receipt</a>@endif</li>@endforeach</ul>
            </flux:card>
        </div>

        <flux:card class="space-y-4"><flux:heading>Notifications</flux:heading>
            <form method="POST" action="{{ route('consumer.notifications.preferences') }}" class="flex flex-wrap items-center gap-4 rounded-lg border border-[#BDECF8] p-3 text-sm dark:border-zinc-700">@csrf @method('PUT')
                <label class="flex items-center gap-2"><input type="checkbox" name="billing" value="1" @checked($consumer->notificationEnabled('billing'))> Billing</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="payment" value="1" @checked($consumer->notificationEnabled('payment'))> Payments</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="support" value="1" @checked($consumer->notificationEnabled('support'))> Support</label>
                <flux:button type="submit" size="sm">Save preferences</flux:button>
                <span>Service notices are always shown.</span>
            </form>
            <ul class="space-y-2 text-sm">
                @foreach ($notifications as $notification)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded border border-[#BDECF8] p-3 dark:border-zinc-700">
                        <span><strong>{{ $notification->data['title'] ?? 'Update' }}</strong> · {{ $notification->data['message'] ?? '' }}</span>
                        @unless ($notification->read_at)<form method="POST" action="{{ route('consumer.notifications.read', $notification->id) }}">@csrf<flux:button size="sm" type="submit">Mark read</flux:button></form>@endunless
                    </li>
                @endforeach
            </ul>
        </flux:card>

        @if ($serviceNotices->isNotEmpty())
            <flux:card class="space-y-3"><flux:heading>Service notices</flux:heading><ul class="space-y-2 text-sm">@foreach ($serviceNotices as $notice)<li><a class="text-[#079EE9] underline" href="{{ route('consumer.service.notice', $notice) }}">Disconnection notice issued {{ $notice->created_at->toDateString() }}</a></li>@endforeach</ul></flux:card>
        @endif
    </main>
</x-layouts::app>
