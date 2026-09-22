<?php

namespace Tests\Feature\Commission;

use App\Models\Order;
use App\Services\Commission\Calculators\SplitCalculator;
use PHPUnit\Framework\TestCase;

/**
 * The classic split-commission bug is rounding: the parts fail to sum to the
 * pool and the books do not balance. This is a pure-arithmetic test, so it
 * runs without the database.
 */
class SplitRoundingTest extends TestCase
{
    private function pool(int $cents): Order
    {
        return new Order(['subtotal_cents' => $cents, 'discount_total_cents' => 0]);
    }

    public function test_a_three_way_split_of_an_indivisible_pool_loses_nothing(): void
    {
        $amounts = (new SplitCalculator)->calculate(
            $this->pool(10001),
            ['rate' => 100, 'shares' => [1 => 1, 2 => 1, 3 => 1]],
            1,
        );

        $this->assertSame(10001, array_sum($amounts));
        $this->assertCount(3, $amounts);
    }

    public function test_every_awkward_pool_and_share_shape_still_sums_exactly(): void
    {
        $calculator = new SplitCalculator;

        $shapes = [
            'even three-way' => [1 => 1, 2 => 1, 3 => 1],
            'seventy thirty' => [1 => 70, 2 => 30],
            'four uneven' => [1 => 50, 2 => 25, 3 => 15, 4 => 10],
            'seven ways' => array_fill_keys(range(1, 7), 1),
        ];

        foreach ([1, 3, 7, 99, 100, 333, 1000, 12345, 99999, 1000003] as $pool) {
            foreach ($shapes as $label => $shares) {
                $amounts = $calculator->calculate($this->pool($pool), ['rate' => 100, 'shares' => $shares], 1);

                $this->assertSame($pool, array_sum($amounts), "{$label} split of {$pool} lost or gained a cent.");
                $this->assertCount(count($shares), $amounts, "{$label} dropped a participant.");
            }
        }
    }

    public function test_nobody_receives_a_negative_or_fractional_amount(): void
    {
        $amounts = (new SplitCalculator)->calculate(
            $this->pool(5),
            ['rate' => 100, 'shares' => [1 => 50, 2 => 25, 3 => 15, 4 => 10]],
            1,
        );

        foreach ($amounts as $amount) {
            $this->assertIsInt($amount);
            $this->assertGreaterThanOrEqual(0, $amount);
        }

        $this->assertSame(5, array_sum($amounts));
    }

    public function test_a_plan_with_no_shares_pays_the_seller_the_whole_pool(): void
    {
        $amounts = (new SplitCalculator)->calculate($this->pool(10000), ['rate' => 5], 42);

        $this->assertSame([42 => 500], $amounts);
    }
}
