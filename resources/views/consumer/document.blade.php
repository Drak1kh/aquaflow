<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>{{ $payment ? 'Receipt '.$payment->reference : $bill->number }}</title><style>body{font:16px sans-serif;color:#062E68;max-width:760px;margin:40px auto;padding:20px}table{width:100%;border-collapse:collapse}td{padding:12px;border-bottom:1px solid #BDECF8}@media print{button{display:none}}</style></head>
<body>
<h1>{{ $bill->waterProvider->name }}</h1><p>{{ $bill->waterProvider->address }} · {{ $bill->waterProvider->phone }}</p>
<h2>{{ $payment ? 'Payment receipt' : 'Water bill' }}</h2>
<p>{{ $bill->consumer->name }} · {{ $bill->consumer->account_number }}<br>{{ $bill->consumer->address }}</p>
@if ($payment)
<p>Receipt: {{ $payment->reference }}<br>Date: {{ $payment->paid_at->format('M d, Y') }}<br>Method: {{ $payment->method }}<br>Received: ₱{{ $payment->amount }}</p>
@endif
<p>Bill {{ $bill->number }} · {{ $bill->period_month->format('M Y') }} · Due {{ $bill->due_at->format('M d, Y') }}</p>
<table><tr><td>Previous / current reading</td><td>{{ $bill->previous_reading }} / {{ $bill->current_reading }}</td></tr><tr><td>Consumption</td><td>{{ $bill->consumption }} m³</td></tr><tr><td>Base charge</td><td>₱{{ $bill->base_charge }}</td></tr><tr><td>Rate per m³</td><td>₱{{ $bill->unit_rate }}</td></tr><tr><td>Consumption charge</td><td>₱{{ $bill->consumption_charge }}</td></tr><tr><td>Penalty</td><td>₱{{ $bill->late_fee }}</td></tr>
@foreach ($bill->adjustments as $adjustment)<tr><td>{{ ucfirst($adjustment->type) }} adjustment · {{ $adjustment->reason }}</td><td>₱{{ $adjustment->amount }}</td></tr>@endforeach
<tr><td>Total</td><td>₱{{ $bill->total }}</td></tr><tr><td>Remaining balance</td><td>₱{{ $bill->balance() }} · {{ $bill->paymentStatus() }}</td></tr></table>
<button onclick="window.print()">Print / save as PDF</button>
</body></html>
