<?php

namespace App\Actions;

use App\Models\Bill;
use App\Models\MeterReading;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DashboardTrends
{
    /**
     * @param  Builder<Bill>  $bills
     * @param  Builder<Payment>|null  $payments
     * @return list<array{month: string, billed: string, collected: string}>
     */
    public function financial(Builder $bills, ?Builder $payments = null): array
    {
        $billed = $this->totals((clone $bills)->where('status', '!=', 'void'), 'period_month', 'total', 2);
        $collected = $payments ? $this->totals((clone $payments)->where('status', 'verified'), 'paid_at', 'amount', 2) : [];

        return array_map(fn (CarbonImmutable $month): array => [
            'month' => $month->format('M Y'),
            'billed' => $billed[$month->format('Y-m')] ?? '0.00',
            'collected' => $collected[$month->format('Y-m')] ?? '0.00',
        ], $this->months());
    }

    /**
     * @param  Builder<MeterReading>  $readings
     * @return list<array{month: string, consumption: string}>
     */
    public function consumption(Builder $readings): array
    {
        $totals = $this->totals($readings, 'period_month', 'current_reading - previous_reading', 3);

        return array_map(fn (CarbonImmutable $month): array => [
            'month' => $month->format('M Y'),
            'consumption' => $totals[$month->format('Y-m')] ?? '0.000',
        ], $this->months());
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @param  literal-string  $dateColumn
     * @param  literal-string  $expression
     * @return array<string, numeric-string>
     */
    private function totals(Builder $query, string $dateColumn, string $expression, int $precision): array
    {
        $months = $this->months();
        $dailyTotals = (clone $query)
            ->where($dateColumn, '>=', $months[0]->toDateString())
            ->where($dateColumn, '<', $months[5]->addMonth()->toDateString())
            ->selectRaw($dateColumn.' as period, SUM('.$expression.') as total')
            ->groupBy($dateColumn)
            ->pluck('total', 'period');
        $totals = [];

        foreach ($dailyTotals as $period => $total) {
            $total = (string) $total;
            if (! is_numeric($total)) {
                throw new \UnexpectedValueException('Dashboard totals must be numeric.');
            }

            $month = substr((string) $period, 0, 7);
            $totals[$month] = bcadd($totals[$month] ?? '0', $total, $precision);
        }

        return $totals;
    }

    /** @return list<CarbonImmutable> */
    private function months(): array
    {
        $start = CarbonImmutable::today()->startOfMonth()->subMonths(5);

        return array_map(fn (int $offset): CarbonImmutable => $start->addMonths($offset), range(0, 5));
    }
}
