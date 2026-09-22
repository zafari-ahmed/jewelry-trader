<?php

namespace App\Services\Commission;

use App\Services\Commission\Calculators\ProfitBasedCalculator;
use App\Services\Commission\Calculators\SalesBasedCalculator;
use App\Services\Commission\Calculators\SplitCalculator;
use App\Services\Commission\Calculators\TieredCalculator;
use RuntimeException;

class CommissionCalculatorFactory
{
    private const CALCULATORS = [
        'sales_based' => SalesBasedCalculator::class,
        'profit_based' => ProfitBasedCalculator::class,
        'tiered' => TieredCalculator::class,
        'split' => SplitCalculator::class,
    ];

    public static function make(string $type): CommissionCalculator
    {
        if (! isset(self::CALCULATORS[$type])) {
            throw new RuntimeException("No commission calculator for plan type [{$type}].");
        }

        return app(self::CALCULATORS[$type]);
    }
}
