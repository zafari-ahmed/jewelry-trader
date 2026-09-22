<?php

namespace App\Services\Commission;

use App\Models\Commission;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * CSV export for payroll. The column mapping is configuration
 * (commission.payroll_export_column_mapping), because the target payroll
 * system's format is not known yet — a new column is a settings change, not a
 * deploy.
 */
class PayrollExporter
{
    /** Values a mapping may point at. */
    private const RESOLVERS = [
        'user.id' => 'userId',
        'user.name' => 'userName',
        'user.email' => 'userEmail',
        'order.number' => 'orderNumber',
        'amount' => 'amount',
        'amount_cents' => 'amountCents',
        'commissionable' => 'commissionable',
        'status' => 'status',
        'period_end' => 'periodEnd',
        'paid_at' => 'paidAt',
    ];

    public function headers(): array
    {
        return array_keys($this->mapping());
    }

    public function rows(Collection $commissions, ?string $periodEnd = null): array
    {
        return $commissions->map(fn (Commission $c) => $this->row($c, $periodEnd))->all();
    }

    public function toCsv(Collection $commissions, ?string $periodEnd = null): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, $this->headers());

        foreach ($this->rows($commissions, $periodEnd) as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function row(Commission $commission, ?string $periodEnd): array
    {
        $row = [];

        foreach ($this->mapping() as $column => $source) {
            $row[] = $this->resolve($commission, $source, $periodEnd);
        }

        return $row;
    }

    private function resolve(Commission $commission, string $source, ?string $periodEnd): string|int|null
    {
        return match (self::RESOLVERS[$source] ?? null) {
            'userId' => $commission->user_id,
            'userName' => $commission->user?->name,
            'userEmail' => $commission->user?->email,
            'orderNumber' => $commission->order?->order_number,
            'amount' => number_format($commission->amount_cents / 100, 2, '.', ''),
            'amountCents' => $commission->amount_cents,
            'commissionable' => number_format($commission->commissionable_cents / 100, 2, '.', ''),
            'status' => $commission->status,
            'periodEnd' => $periodEnd,
            'paidAt' => $commission->paid_at?->toDateString(),
            // An unmapped source is reported rather than silently blank.
            default => "[unmapped: {$source}]",
        };
    }

    private function mapping(): array
    {
        $mapping = Setting::get('commission.payroll_export_column_mapping', []);

        return is_array($mapping) && $mapping !== [] ? $mapping : [
            'employee_id' => 'user.id',
            'employee_name' => 'user.name',
            'period_end' => 'period_end',
            'amount' => 'amount',
        ];
    }
}
