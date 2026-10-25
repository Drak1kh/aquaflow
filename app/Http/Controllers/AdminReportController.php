<?php

namespace App\Http\Controllers;

use App\CsvCell;
use App\DecimalAmount;
use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\WaterProvider;
use App\PlatformMetrics;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function index(Request $request, PlatformMetrics $metrics): View
    {
        $this->authorizeAdmin($request);
        $filters = $this->filters($request);
        $bills = $this->scoped(Bill::query(), $filters);
        $payments = $this->scoped(Payment::query(), $filters);

        return view('pages.admin-reports', [
            'filters' => $filters,
            'providers' => WaterProvider::orderBy('name')->get(),
            'providerRows' => WaterProvider::query()
                ->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
                ->withCount([
                    'consumers as report_consumers_count' => fn ($query) => $this->dates($query, $filters),
                    'bills as report_bills_count' => fn ($query) => $this->dates($query->where('status', '!=', 'void'), $filters),
                ])
                ->withSum(['bills as report_billed_total' => fn ($query) => $this->dates($query->where('status', '!=', 'void'), $filters)], 'total_amount')
                ->withSum(['payments as report_collected_total' => fn ($query) => $this->dates($query->where('status', 'verified'), $filters)], 'amount')
                ->orderBy('name')->get(),
            'outstandingByProvider' => $metrics->outstandingByProvider(),
            'providerCount' => WaterProvider::query()
                ->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
                ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
                ->count(),
            'consumerCount' => $this->scoped(Consumer::query(), $filters)->count(),
            'billCount' => (clone $bills)->where('status', '!=', 'void')->count(),
            'billedCents' => DecimalAmount::units((string) (clone $bills)->where('status', '!=', 'void')->sum('total_amount'), 2),
            'paymentCount' => (clone $payments)->where('status', 'verified')->count(),
            'collectedCents' => DecimalAmount::units((string) (clone $payments)->where('status', 'verified')->sum('amount'), 2),
            'outstandingCents' => $metrics->outstandingCents(isset($filters['water_provider_id']) ? (int) $filters['water_provider_id'] : null),
            'disputeCount' => $this->scoped(SupportTicket::query(), $filters)->whereNotNull('escalated_at')->count(),
        ]);
    }

    public function export(Request $request, PlatformMetrics $metrics): StreamedResponse
    {
        $this->authorizeAdmin($request);
        $filters = $this->filters($request);
        $type = $request->validate(['type' => ['required', Rule::in(['providers', 'consumers', 'bills', 'payments', 'collections', 'outstanding', 'disputes'])]])['type'];

        return response()->streamDownload(function () use ($filters, $type, $metrics): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('Unable to open CSV output.');
            }

            if ($type === 'providers') {
                fputcsv($output, ['Provider code', 'Provider', 'Status', 'Consumers']);
                WaterProvider::query()->withCount('consumers')
                    ->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
                    ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                    ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
                    ->orderBy('id')->chunkById(200, function ($providers) use ($output): void {
                        foreach ($providers as $provider) {
                            fputcsv($output, [CsvCell::safe($provider->code), CsvCell::safe($provider->name), $provider->status, $provider->consumers_count]);
                        }
                    });
            } elseif ($type === 'consumers') {
                fputcsv($output, ['Provider', 'Account', 'Consumer', 'Status', 'Registered']);
                $this->scoped(Consumer::query(), $filters)->with('provider')->chunkById(200, function ($consumers) use ($output): void {
                    foreach ($consumers as $consumer) {
                        fputcsv($output, [CsvCell::safe($consumer->provider->name), CsvCell::safe($consumer->account_number), CsvCell::safe($consumer->name), $consumer->status, $consumer->created_at?->toDateString()]);
                    }
                });
            } elseif ($type === 'bills') {
                fputcsv($output, ['Provider', 'Bill', 'Consumer', 'Amount', 'Status', 'Created']);
                $this->scoped(Bill::query(), $filters)->with(['provider', 'consumer'])->chunkById(200, function ($bills) use ($output): void {
                    foreach ($bills as $bill) {
                        fputcsv($output, [CsvCell::safe($bill->provider->name), CsvCell::safe($bill->bill_number), CsvCell::safe($bill->consumer->name), $bill->total_amount, $bill->status, $bill->created_at?->toDateString()]);
                    }
                });
            } elseif ($type === 'payments' || $type === 'collections') {
                fputcsv($output, ['Provider', 'Reference', 'Consumer', 'Amount', 'Method', 'Status', 'Recorded']);
                $this->scoped(Payment::query(), $filters)
                    ->when($type === 'collections', fn ($query) => $query->where('status', 'verified'))
                    ->with('consumer.provider')->chunkById(200, function ($payments) use ($output): void {
                        foreach ($payments as $payment) {
                            fputcsv($output, [CsvCell::safe($payment->consumer->provider->name), CsvCell::safe($payment->reference_number), CsvCell::safe($payment->consumer->name), $payment->amount, $payment->method, $payment->status, $payment->created_at?->toDateString()]);
                        }
                    });
            } elseif ($type === 'outstanding') {
                fputcsv($output, ['Provider', 'Current outstanding balance']);
                $balances = $metrics->outstandingByProvider();
                WaterProvider::query()->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
                    ->orderBy('id')->chunkById(200, function ($providers) use ($output, $balances): void {
                        foreach ($providers as $provider) {
                            fputcsv($output, [CsvCell::safe($provider->name), DecimalAmount::format($balances[$provider->id] ?? 0)]);
                        }
                    });
            } else {
                fputcsv($output, ['Provider', 'Consumer', 'Type', 'Subject', 'Status', 'Escalated']);
                $this->scoped(SupportTicket::query(), $filters)->whereNotNull('escalated_at')
                    ->with('consumer.provider')->chunkById(200, function ($tickets) use ($output): void {
                        foreach ($tickets as $ticket) {
                            fputcsv($output, [CsvCell::safe($ticket->consumer->provider->name), CsvCell::safe($ticket->consumer->name), $ticket->type, CsvCell::safe($ticket->subject), $ticket->status, $ticket->escalated_at]);
                        }
                    });
            }

            fclose($output);
        }, 'aquaflow-'.$type.'-report.csv', ['Content-Type' => 'text/csv']);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->is_super_admin && $request->user()->status === 'active', 403);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'water_provider_id' => ['nullable', 'integer', Rule::exists('water_providers', 'id')],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function scoped(Builder $query, array $filters): Builder
    {
        return $this->dates($query
            ->when($filters['water_provider_id'] ?? null, fn ($query, $id) => $query->where('water_provider_id', $id)), $filters);
    }

    /** @param array<string, mixed> $filters */
    private function dates(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
    }
}
