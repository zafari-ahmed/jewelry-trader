<?php

namespace App\Console\Commands;

use App\Models\AppraisalRequest;
use App\Models\Commission;
use App\Models\CommissionPlan;
use App\Models\Customer;
use App\Models\InventoryLock;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Order;
use App\Models\Override;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\StaffCommissionAssignment;
use App\Models\User;
use App\Services\Payments\Tender;
use App\Services\Pos\Cart;
use App\Services\Pos\PosSaleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;

/**
 * Loads a worked example of the whole business into an empty system, so the
 * workflow can be walked end to end without first typing a day's inventory.
 *
 * Everything it writes is marked. Products are `DEMO-####`, people are on
 * `@demo.invalid` — a reserved domain that cannot receive mail — and `--purge`
 * removes the lot. That matters: a dashboard quoting invented figures is
 * exactly the confusion this project has already had once, so demo data that
 * cannot be told apart from real data is worse than none.
 *
 * Sales go through PosSaleService with cash tenders, so the orders, payments,
 * stock movements and commissions are all produced by the real code paths and
 * no gateway is ever called.
 */
class SeedDemoData extends Command
{
    protected $signature = 'jt:demo-data
                            {--purge : Remove everything this command created, and nothing else}
                            {--staff : Also create one demo login per role}
                            {--force : Skip the confirmation}';

    protected $description = 'Load (or remove) a marked set of demo records for walking the workflow';

    private const SKU_PREFIX = 'DEMO-';

    private const EMAIL_DOMAIN = '@demo.invalid';

    public function handle(): int
    {
        return $this->option('purge') ? $this->purge() : $this->load();
    }

    private function load(): int
    {
        if (Product::where('sku', 'like', self::SKU_PREFIX.'%')->exists()) {
            $this->components->error('Demo data is already loaded. Run with --purge first.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! confirm('Load demo records into '.config('database.connections.'.config('database.default').'.database').'?')) {
            return self::FAILURE;
        }

        $staffPassword = null;

        if ($this->option('staff')) {
            // Never a fixed password in the source: these are real logins, and
            // they outlive the demo unless somebody purges them.
            $staffPassword = password('Password for the demo staff logins', required: true);
        }

        DB::transaction(function () use ($staffPassword) {
            $users = $this->actors($this->staff($staffPassword));
            $customers = $this->customers();
            $products = $this->products($users['appraiser'] ?? null);

            $this->commissionPlan($users);
            $this->sales($products, $customers, $users);
            $this->exceptions($products, $users);
            $this->enquiries($products, $customers);
        });

        $this->newLine();
        $this->components->info('Demo data loaded.');
        $this->table(['What', 'Count'], $this->tally());
        $this->newLine();
        $this->line('  Everything above is marked <options=bold>'.self::SKU_PREFIX.'</> or <options=bold>'.self::EMAIL_DOMAIN.'</>.');
        $this->line('  Remove it with <options=bold>php artisan jt:demo-data --purge</>.');

        if ($this->option('staff')) {
            $this->newLine();
            $this->line('  Demo logins (one per role), all on the password you just set:');

            foreach (User::where('email', 'like', '%'.self::EMAIL_DOMAIN)->get() as $user) {
                $this->line(sprintf('    %-34s %s', $user->email, $user->roles->pluck('name')->implode(', ')));
            }
        }

        return self::SUCCESS;
    }

    /** @return array<string, User> */
    private function staff(?string $password): array
    {
        if (! $password) {
            return [];
        }

        $locations = Location::pluck('id', 'slug');

        $people = [
            'manager' => ['Margaret Renner', 'store-manager', 'madison-ave'],
            'sales' => ['Alice Whitfield', 'sales-staff', 'madison-ave'],
            'appraiser' => ['Jonas Ortega', 'inventory-specialist', 'workshop'],
            'accounts' => ['Deborah Vance', 'accountant', 'madison-ave'],
            'service' => ['Samuel Okonjo', 'customer-service', 'greenwich'],
        ];

        $created = [];

        foreach ($people as $key => [$name, $role, $locationSlug]) {
            $user = User::create([
                'name' => $name,
                'email' => str($name)->lower()->replace(' ', '.')->append(self::EMAIL_DOMAIN)->value(),
                'password' => $password,
                'location_id' => $locations[$locationSlug] ?? null,
                'email_verified_at' => now(),
            ]);

            $user->assignRole($role);
            $created[$key] = $user->fresh();
        }

        return $created;
    }

    /**
     * Who the demo records are attributed to. The staff logins are optional,
     * but the overrides, locks and commissions are not — without somebody to
     * hang them on, the parts of the system that are most worth seeing would
     * be the parts that came up empty. So they fall back to whoever is
     * already here, by role where possible.
     *
     * @param  array<string, User>  $demoStaff
     * @return array<string, User>
     */
    private function actors(array $demoStaff): array
    {
        $fallback = User::query()->oldest('id')->first();

        if (! $fallback) {
            return $demoStaff;
        }

        $byRole = [
            'manager' => 'store-manager',
            'sales' => 'sales-staff',
            'appraiser' => 'inventory-specialist',
            'accounts' => 'accountant',
            'service' => 'customer-service',
        ];

        foreach ($byRole as $key => $role) {
            $demoStaff[$key] ??= User::role($role)->oldest('id')->first() ?? $fallback;
        }

        return $demoStaff;
    }

    /** @return array<int, Customer> */
    private function customers(): array
    {
        $people = [
            ['Eleanor Whitcombe', 'eleanor', '+1 212 555 0118', 'Buys Edwardian. Ring size 6.'],
            ['Marcus Delaney', 'marcus', '+1 212 555 0174', 'Collects pocket watches.'],
            ['Priya Raghunathan', 'priya', '+1 917 555 0133', 'Asked to be told about Art Deco sapphires.'],
            ['Thomas Ardern', 'thomas', '+1 203 555 0192', 'Greenwich walk-in. Prefers to be called.'],
        ];

        return array_map(fn (array $p) => Customer::create([
            'name' => $p[0],
            'email' => $p[1].self::EMAIL_DOMAIN,
            'phone' => $p[2],
            'notes' => $p[3],
            'address' => ['line1' => '— demo record —', 'city' => 'New York', 'state' => 'NY'],
        ]), $people);
    }

    /**
     * A spread across every status, so the review queue, the storefront and
     * the register all have something in them. Two are deliberately awkward:
     * one draft missing its required fields, and one lab-grown ruby with no
     * treatment recorded, which the listing gate refuses until somebody
     * answers for it.
     *
     * @return array<string, Product>
     */
    private function products(?User $appraiser): array
    {
        $locations = Location::pluck('id', 'slug');

        $catalogue = [
            ['ring-edwardian', 'Edwardian Diamond Cluster Ring', 'Rings', 'Edwardian', 'Platinum', 'listed', 680000, 'madison-ave',
                [['stone_type' => 'Diamond', 'origin' => 'Natural', 'cut' => 'Old European', 'estimated_weight_ct' => 1.15, 'color' => 'H', 'clarity' => 'VS2', 'cut_grade' => 'Very Good']]],

            ['brooch-mourning', 'Victorian Mourning Brooch', 'Brooches', 'Victorian', 'Gold 15k', 'pending_review', 145000, 'workshop', []],

            ['bracelet-deco', 'Art Deco Sapphire Line Bracelet', 'Bracelets', 'Art Deco', 'Platinum', 'listed', 1240000, 'madison-ave',
                [['stone_type' => 'Sapphire', 'origin' => 'Natural', 'cut' => 'Emerald Cut', 'estimated_weight_ct' => 3.40, 'treatment' => 'Heated']]],

            ['pendant-ruby', 'Ruby Teardrop Pendant', 'Necklaces', 'Contemporary', 'Gold 18k', 'approved', 320000, 'workshop',
                // No treatment recorded, and a ruby needs one: this will not list.
                [['stone_type' => 'Ruby', 'origin' => 'Lab-Grown', 'cut' => 'Pear', 'estimated_weight_ct' => 2.10]]],

            ['band-plain', 'Gold Wedding Band', 'Rings', null, 'Gold 14k', 'draft', null, 'greenwich', []],

            ['ring-emerald', 'Mid-Century Emerald Cocktail Ring', 'Rings', 'Mid-Century', 'Gold 18k', 'listed', 890000, 'madison-ave',
                [['stone_type' => 'Emerald', 'origin' => 'Natural', 'cut' => 'Emerald Cut', 'estimated_weight_ct' => 2.75, 'treatment' => 'Oiled']]],

            ['necklace-pearl', 'Georgian Seed Pearl Necklace', 'Necklaces', 'Georgian', 'Gold 9k', 'listed', 410000, 'greenwich', []],

            ['watch-retro', 'Retro Gold Dress Watch', 'Watches', 'Retro', 'Gold 14k', 'listed', 275000, 'madison-ave', []],

            ['cuff-silver', 'Silver Open Cuff', 'Bracelets', 'Contemporary', 'Sterling Silver', 'listed', 68000, 'greenwich', []],

            ['earrings-rose', 'Rose-Cut Diamond Drop Earrings', 'Earrings', 'Victorian', 'Gold 18k', 'listed', 520000, 'workshop',
                [['stone_type' => 'Diamond', 'origin' => 'Natural', 'cut' => 'Rose Cut', 'estimated_weight_ct' => 0.90, 'color' => 'J', 'clarity' => 'SI1']]],

            ['solitaire-mine', 'Old Mine Cut Solitaire', 'Rings', 'Victorian', 'Gold 18k', 'listed', 1450000, 'madison-ave',
                [['stone_type' => 'Diamond', 'origin' => 'Natural', 'cut' => 'Old Mine', 'estimated_weight_ct' => 2.05, 'color' => 'I', 'clarity' => 'VS1']]],

            ['brooch-panther', 'Panther Brooch', 'Brooches', 'Mid-Century', 'Gold 18k', 'approved', 1980000, 'workshop', []],
        ];

        $products = [];
        $number = 1;

        foreach ($catalogue as [$key, $title, $category, $period, $metal, $status, $priceCents, $locationSlug, $stones]) {
            $product = Product::create([
                'sku' => self::SKU_PREFIX.str_pad((string) $number++, 4, '0', STR_PAD_LEFT),
                'title' => $title,
                'category' => $category,
                'style_period' => $period,
                'metal_type' => $metal,
                'measurements' => $status === 'draft' ? null : 'Demo record — measurements not surveyed',
                'condition_notes' => 'Demo record. Condition not assessed.',
                'customer_description' => $status === 'draft' ? null : "A demonstration record for {$title}. Not a real piece and not for sale.",
                'internal_description' => 'Created by jt:demo-data.',
                'status' => $status,
                'created_by' => $appraiser?->id,
                'approved_by' => in_array($status, ['approved', 'listed'], true) ? $appraiser?->id : null,
                'submitted_for_review_at' => $status === 'draft' ? null : now()->subDays(random_int(1, 20)),
            ]);

            if ($priceCents) {
                Pricing::create([
                    'product_id' => $product->id,
                    'acquisition_value_cents' => (int) round($priceCents * 0.45),
                    'retail_price_cents' => $priceCents,
                    'insurance_value_cents' => (int) round($priceCents * 1.30),
                    'negotiation_min_cents' => (int) round($priceCents * 0.85),
                    'priced_by' => $appraiser?->id,
                ]);
            }

            InventoryStock::create([
                'product_id' => $product->id,
                'location_id' => $locations[$locationSlug],
                'quantity' => 1,
                'status' => 'in_stock',
            ]);

            foreach ($stones as $position => $stone) {
                $product->gemstones()->create($stone + ['is_primary' => $position === 0]);
            }

            $products[$key] = $product->fresh();
        }

        return $products;
    }

    /** @param array<string, User> $users */
    private function commissionPlan(array $users): void
    {
        if (! isset($users['sales'])) {
            return;
        }

        $plan = CommissionPlan::create([
            'name' => 'Demo — 4% of sale',
            'type' => 'sales_based',
            'config' => ['rate_percent' => 4],
            'is_active' => true,
        ]);

        foreach (['sales', 'manager'] as $key) {
            if (! isset($users[$key])) {
                continue;
            }

            // Labor Code §2751 wants the agreed terms, not only the number.
            StaffCommissionAssignment::create([
                'user_id' => $users[$key]->id,
                'commission_plan_id' => $plan->id,
                'effective_from' => now()->startOfYear(),
                'terms_text' => 'Demo agreement. 4% of the sale value of each piece sold, '
                    .'calculated when the order is paid and payable with the following payroll run.',
            ]);
        }
    }

    /**
     * Real sales through the register, tendered in cash so no gateway is
     * touched. One of them is then returned, which exercises the refund path
     * and puts something in the returns history.
     *
     * @param  array<string, Product>  $products
     * @param  array<int, Customer>  $customers
     * @param  array<string, User>  $users
     */
    private function sales(array $products, array $customers, array $users): void
    {
        $register = app(PosSaleService::class);
        $seller = $users['sales'] ?? null;

        $basket = [
            ['watch-retro', 0],
            ['cuff-silver', 1],
            ['necklace-pearl', 3],
        ];

        $orders = [];

        foreach ($basket as [$productKey, $customerIndex]) {
            $product = $products[$productKey];
            $cart = new Cart($product->stock()->value('location_id'));
            $cart->addProduct($product);

            $orders[$productKey] = $register->complete(
                cart: $cart,
                tenders: [Tender::cash($cart->totalCents(), $cart->totalCents())],
                customerId: $customers[$customerIndex]->id,
                userId: $seller?->id,
            );
        }

        // Returned the next day: the piece goes back on the shelf and the
        // money goes back to the customer.
        $register->refund($orders['cuff-silver'], userId: $users['manager']->id ?? null);

        if ($seller) {
            $this->backdateCommissions($seller);
        }
    }

    /**
     * Commission rows are calculated by a queued job, which on a server with
     * no worker running yet would leave the report empty. These are written
     * directly so there is something to look at either way.
     */
    private function backdateCommissions(User $seller): void
    {
        $plan = CommissionPlan::where('name', 'like', 'Demo%')->first();

        if (! $plan) {
            return;
        }

        foreach (Order::whereIn('status', ['paid', 'fulfilled'])->get() as $order) {
            if (Commission::where('order_id', $order->id)->exists()) {
                continue;
            }

            Commission::create([
                'order_id' => $order->id,
                'user_id' => $seller->id,
                'commission_plan_id' => $plan->id,
                'amount_cents' => (int) round($order->subtotal_cents * 0.04),
                'status' => 'pending',
                'calculated_at' => now(),
            ]);
        }
    }

    /**
     * The things that are meant to be rare: a price overridden with a reason,
     * and a piece held back from sale.
     *
     * @param  array<string, Product>  $products
     * @param  array<string, User>  $users
     */
    private function exceptions(array $products, array $users): void
    {
        if (! isset($users['sales'], $users['manager'], $users['appraiser'])) {
            return;
        }

        Override::create([
            'product_id' => $products['ring-emerald']->id,
            'override_type' => 'price',
            'reason' => 'Demo record. Agreed at the trade show below the negotiation floor.',
            'requested_by' => $users['sales']->id,
            'approved_by' => $users['manager']->id,
            'amount' => 25000,
            'status' => 'approved',
            'approved_at' => now()->subDays(3),
        ]);

        InventoryLock::create([
            'product_id' => $products['brooch-panther']->id,
            'lock_type' => 'sales',
            'reason' => 'Demo record. Awaiting an independent valuation before it is offered.',
            'locked_by' => $users['appraiser']->id,
            'locked_at' => now()->subDays(2),
        ]);
    }

    /**
     * @param  array<string, Product>  $products
     * @param  array<int, Customer>  $customers
     */
    private function enquiries(array $products, array $customers): void
    {
        AppraisalRequest::create([
            'product_id' => $products['bracelet-deco']->id,
            'customer_id' => $customers[2]->id,
            'name' => $customers[2]->name,
            'email' => $customers[2]->email,
            'message' => 'Demo record. Could you send the appraisal document and the sapphire certificate?',
            'status' => 'pending',
        ]);
    }

    /**
     * Removes what this command made, identified by its markers, and nothing
     * else. Orders go first because their items point at the products.
     *
     * The audit trail is left alone on purpose. Entries about demo records are
     * a true account of what happened to the database, and a log that can be
     * cleared is not a log.
     */
    private function purge(): int
    {
        $products = Product::where('sku', 'like', self::SKU_PREFIX.'%')->pluck('id');
        $customers = Customer::where('email', 'like', '%'.self::EMAIL_DOMAIN)->pluck('id');
        $users = User::where('email', 'like', '%'.self::EMAIL_DOMAIN)->pluck('id');

        $orders = Order::query()
            ->whereHas('items', fn ($q) => $q->whereIn('product_id', $products))
            ->orWhereIn('customer_id', $customers)
            ->pluck('id');

        if ($products->isEmpty() && $customers->isEmpty() && $users->isEmpty()) {
            $this->components->info('There is no demo data to remove.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! confirm(
            sprintf(
                'Remove %d products, %d orders, %d customers and %d logins?',
                $products->count(), $orders->count(), $customers->count(), $users->count(),
            ),
        )) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($products, $customers, $users, $orders) {
            Commission::whereIn('order_id', $orders)->delete();
            Override::whereIn('product_id', $products)->delete();
            InventoryLock::whereIn('product_id', $products)->delete();
            AppraisalRequest::whereIn('product_id', $products)->orWhereIn('customer_id', $customers)->delete();
            Order::whereIn('id', $orders)->each(fn (Order $order) => $order->delete());
            Product::whereIn('id', $products)->each(fn (Product $product) => $product->delete());
            Customer::whereIn('id', $customers)->delete();
            StaffCommissionAssignment::whereIn('user_id', $users)->delete();
            Commission::whereIn('user_id', $users)->delete();
            CommissionPlan::where('name', 'like', 'Demo%')->delete();
            User::whereIn('id', $users)->delete();
        });

        $this->components->info('Demo data removed. Audit entries about it are kept.');

        return self::SUCCESS;
    }

    /**
     * Only the demo records. Reporting the whole table would overstate what
     * this command did on a database that already had something in it.
     *
     * @return array<int, array<int, string|int>>
     */
    private function tally(): array
    {
        $products = Product::where('sku', 'like', self::SKU_PREFIX.'%');
        $productIds = $products->clone()->pluck('id');
        $customers = Customer::where('email', 'like', '%'.self::EMAIL_DOMAIN);

        $orders = Order::whereHas('items', fn ($q) => $q->whereIn('product_id', $productIds));

        return [
            ['Products', $products->clone()->count()],
            ['— listed publicly', $products->clone()->where('status', 'listed')->count()],
            ['— awaiting review', $products->clone()->where('status', 'pending_review')->count()],
            ['— draft, incomplete', $products->clone()->where('status', 'draft')->count()],
            ['Customers', $customers->count()],
            ['Orders', $orders->clone()->count()],
            ['— refunded', $orders->clone()->whereIn('status', ['refunded', 'partially_refunded'])->count()],
            ['Commission rows', Commission::whereIn('order_id', $orders->clone()->pluck('id'))->count()],
            ['Overrides', Override::whereIn('product_id', $productIds)->count()],
            ['Inventory locks', InventoryLock::whereIn('product_id', $productIds)->count()],
        ];
    }
}
