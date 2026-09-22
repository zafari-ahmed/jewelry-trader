<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * Retention is per category, because financial records outlive general
 * activity: the IRS expects seven years on payment, refund and commission
 * records, while routine activity need not be kept that long.
 *
 * Both windows are settings, editable in Settings → Security with no deploy.
 */
class PurgeAuditLogs extends Command
{
    protected $signature = 'audit:purge {--dry-run : Report what would be removed without deleting}';

    protected $description = 'Delete audit entries past their category retention window';

    public function handle(): int
    {
        $windows = [
            'financial' => (int) Setting::get('security.audit_retention_days_financial', 2557),
            'default' => (int) Setting::get('security.audit_retention_days', 730),
        ];

        $total = 0;

        foreach ($windows as $category => $days) {
            $cutoff = now()->subDays($days);

            $query = AuditLog::query()->where('created_at', '<', $cutoff);

            $category === 'financial'
                ? $query->where('category', 'financial')
                : $query->where('category', '!=', 'financial');

            $count = (clone $query)->count();
            $total += $count;

            $this->line(sprintf(
                '  %-10s %s older than %s days%s',
                $category,
                str_pad(number_format($count), 8, ' ', STR_PAD_LEFT),
                number_format($days),
                $this->option('dry-run') ? ' (dry run)' : '',
            ));

            if (! $this->option('dry-run') && $count > 0) {
                $query->delete();
            }
        }

        $this->info($this->option('dry-run')
            ? number_format($total).' entries are past retention.'
            : number_format($total).' entries removed.');

        return self::SUCCESS;
    }
}
