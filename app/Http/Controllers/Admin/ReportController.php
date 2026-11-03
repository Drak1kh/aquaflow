<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\WaterProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** @var list<string> */
    private const TYPES = ['providers', 'consumers', 'billing', 'payments', 'collections', 'balances', 'performance', 'disputes'];

    /** @var array<string, list<string>> */
    private const HEADERS = [
        'providers' => ['Provider', 'Code', 'Status', 'Consumers', 'Users'],
        'consumers' => ['Account', 'Name', 'Provider', 'Status'],
        'billing' => ['Bill', 'Provider', 'Consumer', 'Month', 'Billed PHP', 'Balance PHP', 'Status'],
        'balances' => ['Bill', 'Provider', 'Consumer', 'Month', 'Billed PHP', 'Balance PHP', 'Status'],
        'payments' => ['Reference', 'Provider', 'Consumer', 'Paid at', 'Amount PHP', 'Status'],
        'collections' => ['Provider', 'Reference', 'Method', 'Paid at', 'Collected PHP'],
        'performance' => ['Provider', 'Consumers', 'Billed PHP', 'Collected PHP', 'Outstanding PHP'],
        'disputes' => ['Ticket', 'Provider', 'Consumer', 'Type', 'Status'],
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('admin.reports', [
            'filters' => $filters,
            'types' => self::TYPES,
            'headers' => self::HEADERS[$filters['type']],
            'providers' => WaterProvider::orderBy('name')->get(),
            'rows' => collect($this->rows($filters)),
        ]);
    }

    public function print(Request $request): View
    {
        $filters = $this->filters($request);

        return view('admin.report-print', [
            'filters' => $filters,
            'headers' => self::HEADERS[$filters['type']],
            'rows' => collect($this->rows($filters)),
            'providerName' => $filters['provider'] ? WaterProvider::findOrFail($filters['provider'])->name : null,
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = collect($this->rows($filters));

        return response()->streamDownload(function () use ($rows, $filters): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open report output.');
            }
            fputcsv($stream, self::HEADERS[$filters['type']]);
            foreach ($rows as $row) {
                fputcsv($stream, array_map(static function ($value): string {
                    $text = (string) $value;

                    return preg_match('/^[=+\-@\t\r]/', $text) ? "'".$text : $text;
                }, $row));
            }
            fclose($stream);
        }, $filters['type'].'-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{type: string, provider: int|null, from: string|null, to: string|null} */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in(self::TYPES)],
            'provider' => ['nullable', 'integer', 'exists:water_providers,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            'type' => $data['type'] ?? 'providers',
            'provider' => isset($data['provider']) ? (int) $data['provider'] : null,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
        ];
    }

    /** @param array{type: string, provider: int|null, from: string|null, to: string|null} $filters
     * @return list<array<string, scalar|null>>
     */
    private function rows(array $filters): array
    {
        $provider = $filters['provider'];
        $from = $filters['from'];
        $to = $filters['to'];

        return array_values((match ($filters['type']) {
            'providers' => WaterProvider::withCount(['consumers', 'users'])
                ->when($provider, fn ($query) => $query->whereKey($provider))->orderBy('name')->get()
                ->map(fn (WaterProvider $item) => ['Provider' => $item->name, 'Code' => $item->code, 'Status' => $item->is_active ? 'Active' : 'Inactive', 'Consumers' => $item->consumers_count, 'Users' => $item->users_count]),
            'consumers' => Consumer::with('waterProvider')->when($provider, fn ($query) => $query->where('water_provider_id', $provider))->orderBy('name')->get()
                ->map(fn (Consumer $item) => ['Account' => $item->account_number, 'Name' => $item->name, 'Provider' => $item->waterProvider->name, 'Status' => $item->is_active ? 'Active' : 'Inactive']),
            'billing', 'balances' => Bill::with(['waterProvider', 'consumer', 'allocations'])
                ->when($provider, fn ($query) => $query->where('water_provider_id', $provider))
                ->when($from, fn ($query) => $query->whereDate('period_month', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('period_month', '<=', $to))
                ->where('status', '!=', 'void')->when($filters['type'] === 'balances', fn ($q) => $q->whereRaw('total > (select coalesce(sum(amount), 0) from payment_allocations where bill_id = bills.id)'))->latest('id')->get()
                ->map(fn (Bill $item) => ['Bill' => $item->number, 'Provider' => $item->waterProvider->name, 'Consumer' => $item->consumer->name, 'Month' => $item->period_month->format('Y-m'), 'Billed PHP' => $item->total, 'Balance PHP' => $item->balance(), 'Status' => $item->paymentStatus()]),
            'payments' => Payment::with(['waterProvider', 'consumer'])
                ->when($provider, fn ($query) => $query->where('water_provider_id', $provider))
                ->when($from, fn ($query) => $query->whereDate('paid_at', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('paid_at', '<=', $to))
                ->latest('id')->get()
                ->map(fn (Payment $item) => ['Reference' => $item->reference, 'Provider' => $item->waterProvider->name, 'Consumer' => $item->consumer->name, 'Paid at' => $item->paid_at->toDateString(), 'Amount PHP' => $item->amount, 'Status' => $item->status]),
            'collections' => Payment::with('waterProvider')->where('status', 'verified')
                ->when($provider, fn ($query) => $query->where('water_provider_id', $provider))
                ->when($from, fn ($query) => $query->whereDate('paid_at', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('paid_at', '<=', $to))
                ->latest('id')->get()
                ->map(fn (Payment $item) => ['Provider' => $item->waterProvider->name, 'Reference' => $item->reference, 'Method' => ucfirst(str_replace('_', ' ', $item->method)), 'Paid at' => $item->paid_at->toDateString(), 'Collected PHP' => $item->amount]),
            'performance' => WaterProvider::withCount('consumers')->when($provider, fn ($query) => $query->whereKey($provider))->orderBy('name')->get()
                ->map(function (WaterProvider $item) use ($from, $to): array {
                    $bills = Bill::with('allocations')->where('water_provider_id', $item->id)->where('status', '!=', 'void')
                        ->when($from, fn ($query) => $query->whereDate('period_month', '>=', $from))
                        ->when($to, fn ($query) => $query->whereDate('period_month', '<=', $to))->get();
                    $payments = Payment::where('water_provider_id', $item->id)->where('status', 'verified')
                        ->when($from, fn ($query) => $query->whereDate('paid_at', '>=', $from))
                        ->when($to, fn ($query) => $query->whereDate('paid_at', '<=', $to));
                    $billed = $bills->reduce(fn ($sum, Bill $bill) => bcadd($sum, $bill->total, 2), '0.00');
                    $collected = (string) $payments->sum('amount');
                    $outstanding = $bills->reduce(fn (string $carry, Bill $bill): string => bcadd($carry, $bill->balance(), 2), '0.00');

                    return ['Provider' => $item->name, 'Consumers' => $item->consumers_count, 'Billed PHP' => $billed, 'Collected PHP' => $collected, 'Outstanding PHP' => $outstanding];
                }),
            'disputes' => SupportTicket::with(['waterProvider', 'consumer'])->whereNotNull('escalated_at')
                ->when($provider, fn ($query) => $query->where('water_provider_id', $provider))
                ->when($from, fn ($query) => $query->whereDate('escalated_at', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('escalated_at', '<=', $to))
                ->latest('id')->get()
                ->map(fn (SupportTicket $item) => ['Ticket' => $item->id, 'Provider' => $item->waterProvider->name, 'Consumer' => $item->consumer->name, 'Type' => $item->type, 'Status' => $item->status]),
            default => throw new \InvalidArgumentException('Unknown report type.'),
        })->all());
    }
}
