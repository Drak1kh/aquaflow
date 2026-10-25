<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ ucfirst($filters['type']) }} report — {{ app(\App\Actions\PlatformSettings::class)->get('platform_name') }}</title><style>body{font:14px/1.5 Arial,sans-serif;color:#062e68;margin:32px}h1{font-size:24px;margin:0 0 8px}.meta{color:#405e75;margin-bottom:24px}table{width:100%;border-collapse:collapse;font-size:12px}th,td{text-align:left;padding:9px;border-bottom:1px solid #dceaf3}th{background:#f7fcfe}button{padding:8px 14px;margin-bottom:20px}@media print{button{display:none}body{margin:12mm}}</style></head><body><button onclick="window.print()">Print / save PDF</button><h1>{{ ucfirst($filters['type']) }} report</h1><div class="meta">{{ app(\App\Actions\PlatformSettings::class)->get('platform_name') }} · {{ now()->format(app(\App\Actions\PlatformSettings::class)->get('date_format')) }}</div><table>
<thead><tr>
@foreach ($headers as $heading)<th>{{ $heading }}</th>
@endforeach</tr></thead>
<tbody>
@forelse ($rows as $row)<tr>
@foreach ($row as $value)<td>{{ $value }}</td>
@endforeach</tr>
@empty<tr><td>No records match this report.</td></tr>
@endforelse</tbody></table></body></html>
