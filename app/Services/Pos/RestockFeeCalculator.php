<?php

namespace App\Services\Pos;

use App\Models\Setting;

/**
 * What to withhold from a refund.
 *
 * Every figure comes from settings (rule 3.1) because this is a commercial
 * policy, not arithmetic: the percentage, the cap, whether exchanges and cash
 * returns are included, and which reasons waive it.
 *
 * Two rules are deliberately not configurable.
 *
 * The fee is calculated on the goods price **before tax**, and the tax is
 * refunded in full regardless. A return reverses the sale, so the whole of the
 * sales tax the customer paid is owed back to them; the fee is a separate
 * service charge. Reducing the refunded tax in proportion would mean keeping
 * tax money that still has to be remitted.
 *
 * And a waiver always carries a reason. There is no way to ask for nothing to
 * be withheld without saying why.
 */
class RestockFeeCalculator
{
    /**
     * @param  bool  $isExchange  the return is part of a swap, not a refund
     * @param  string|null  $waiverReason  one of the configured reasons
     * @param  string|null  $method  how the original sale was paid, when known
     */
    public function feeFor(
        int $goodsCents,
        bool $isExchange = false,
        ?string $waiverReason = null,
        ?string $method = null,
    ): int {
        if ($waiverReason !== null || $goodsCents <= 0) {
            return 0;
        }

        if ($isExchange && ! Setting::get('pos.restock_fee_applies_to_exchanges', false)) {
            return 0;
        }

        if ($method === 'cash' && ! Setting::get('pos.restock_fee_applies_to_cash', true)) {
            return 0;
        }

        $percent = (int) Setting::get('pos.restock_fee_percent', 0);

        if ($percent <= 0) {
            return 0;
        }

        $fee = (int) round($goodsCents * $percent / 100);
        $cap = (int) Setting::get('pos.restock_fee_cap_cents', 0);

        if ($cap > 0) {
            $fee = min($fee, $cap);
        }

        // Never more than the goods themselves, whatever the settings say.
        return min($fee, $goodsCents);
    }

    /** @return array<int, string> */
    public function waiverReasons(): array
    {
        $reasons = Setting::get('pos.restock_fee_waiver_reasons', []);

        return is_array($reasons) ? array_values($reasons) : [];
    }

    public function isConfigured(): bool
    {
        return (int) Setting::get('pos.restock_fee_percent', 0) > 0;
    }

    public function percent(): int
    {
        return (int) Setting::get('pos.restock_fee_percent', 0);
    }

    public function capCents(): int
    {
        return (int) Setting::get('pos.restock_fee_cap_cents', 0);
    }
}
