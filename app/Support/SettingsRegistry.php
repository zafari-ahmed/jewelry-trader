<?php

namespace App\Support;

/**
 * Describes which settings exist, their type and their seeded default.
 *
 * This is metadata, not configuration: the *values* live in the settings table
 * and are edited from the admin panel (rule 3.1). This registry only tells the
 * seeder and the settings UI what fields to render and how to cast them.
 */
class SettingsRegistry
{
    /**
     * @return array<string, array{type:string, default:mixed, label:string, help?:string}>
     */
    public static function all(): array
    {
        return array_merge(
            self::general(),
            self::pos(),
            self::payments(),
            self::ai(),
            self::security(),
            self::commission(),
            self::pricing(),
            self::qc(),
            self::features(),
        );
    }

    public static function group(string $group): array
    {
        return array_filter(
            self::all(),
            fn (string $path) => str_starts_with($path, "{$group}."),
            ARRAY_FILTER_USE_KEY,
        );
    }

    public static function general(): array
    {
        return [
            'general.company_name' => ['type' => 'string', 'default' => 'Jewelry Trader', 'label' => 'Company name'],
            'general.logo_path' => ['type' => 'string', 'default' => null, 'label' => 'Logo'],
            'general.timezone' => ['type' => 'string', 'default' => 'America/Los_Angeles', 'label' => 'Timezone'],
            'general.currency' => ['type' => 'string', 'default' => 'USD', 'label' => 'Default currency', 'help' => 'USD only in Phase 1.'],
            'general.business_hours' => ['type' => 'json', 'default' => [
                'mon' => '10:00-18:00', 'tue' => '10:00-18:00', 'wed' => '10:00-18:00',
                'thu' => '10:00-18:00', 'fri' => '10:00-18:00', 'sat' => '11:00-17:00', 'sun' => 'closed',
            ], 'label' => 'Business hours'],
        ];
    }

    public static function pos(): array
    {
        return [
            'pos.receipt_footer' => [
                'type' => 'string',
                'default' => "ALL ANTIQUE & ESTATE SALES FINAL AFTER 30 DAYS.\nAPPRAISAL DOCUMENTS AVAILABLE ON REQUEST.",
                'label' => 'Receipt footer text',
            ],
            'pos.return_window_days' => ['type' => 'integer', 'default' => 30, 'label' => 'Return window (days)'],
            'pos.max_staff_discount_percent' => [
                'type' => 'integer', 'default' => 10, 'label' => 'Max staff discount (%)',
                'help' => 'Above this, a role with apply-discount-above-threshold must approve.',
            ],
        ];
    }

    public static function payments(): array
    {
        return [
            'payments.active_gateway' => ['type' => 'string', 'default' => 'stripe', 'label' => 'Active gateway'],
            'payments.test_mode' => ['type' => 'boolean', 'default' => true, 'label' => 'Test mode'],
            'payments.accept_card' => ['type' => 'boolean', 'default' => true, 'label' => 'Accept card'],
            'payments.accept_cash' => ['type' => 'boolean', 'default' => true, 'label' => 'Accept cash'],
            'payments.accept_split' => ['type' => 'boolean', 'default' => true, 'label' => 'Accept split payments'],
            // Live and test key pairs are stored separately; test mode switches which pair is read.
            'payments.stripe_publishable_key' => ['type' => 'string', 'default' => null, 'label' => 'Stripe publishable key (live)'],
            'payments.stripe_secret_key' => ['type' => 'encrypted_string', 'default' => null, 'label' => 'Stripe secret key (live)'],
            'payments.stripe_webhook_secret' => ['type' => 'encrypted_string', 'default' => null, 'label' => 'Stripe webhook secret (live)'],
            'payments.stripe_test_publishable_key' => ['type' => 'string', 'default' => null, 'label' => 'Stripe publishable key (test)'],
            'payments.stripe_test_secret_key' => ['type' => 'encrypted_string', 'default' => null, 'label' => 'Stripe secret key (test)'],
            'payments.stripe_test_webhook_secret' => ['type' => 'encrypted_string', 'default' => null, 'label' => 'Stripe webhook secret (test)'],
        ];
    }

    public static function ai(): array
    {
        return [
            'ai.enabled' => ['type' => 'boolean', 'default' => false, 'label' => 'AI features', 'help' => 'AI features are not active in Phase 1.'],
            'ai.provider' => ['type' => 'string', 'default' => null, 'label' => 'Provider'],
            'ai.api_key' => ['type' => 'encrypted_string', 'default' => null, 'label' => 'Provider API key'],
            'ai.vision' => ['type' => 'boolean', 'default' => false, 'label' => 'Photo analysis'],
            'ai.description' => ['type' => 'boolean', 'default' => false, 'label' => 'Description generation'],
            'ai.pricing' => ['type' => 'boolean', 'default' => false, 'label' => 'Comparable-sale pricing'],
            'ai.search' => ['type' => 'boolean', 'default' => false, 'label' => 'Natural-language search'],
            'ai.vision_model' => ['type' => 'string', 'default' => null, 'label' => 'Photo analysis model'],
            'ai.description_model' => ['type' => 'string', 'default' => null, 'label' => 'Description model'],
            'ai.pricing_model' => ['type' => 'string', 'default' => null, 'label' => 'Pricing model'],
            'ai.search_model' => ['type' => 'string', 'default' => null, 'label' => 'Search model'],

            // Connection details. Vendor-neutral: any service speaking the
            // common chat-completions shape works, so switching provider is a
            // settings change rather than a code change (requirement 1).
            'ai.endpoint' => ['type' => 'string', 'default' => null, 'label' => 'API endpoint', 'help' => 'Base URL of the provider, e.g. https://…/v1'],
            'ai.timeout_seconds' => ['type' => 'integer', 'default' => 45, 'label' => 'Request timeout (seconds)'],
            'ai.max_output_tokens' => ['type' => 'integer', 'default' => 1200, 'label' => 'Maximum response length'],
            'ai.min_confidence' => ['type' => 'integer', 'default' => 40, 'label' => 'Minimum confidence to suggest (%)', 'help' => 'Below this, a suggestion is discarded rather than shown.'],

            // What the service costs, so a pilot can be judged on real spend
            // rather than on a quoted price. Suppliers quote per million
            // tokens; rows already written keep the rate they were charged at.
            'ai.track_usage' => ['type' => 'boolean', 'default' => true, 'label' => 'Record what each call costs'],
            'ai.cost_per_million_input' => ['type' => 'string', 'default' => '0', 'label' => 'Cost per million input tokens ($)'],
            'ai.cost_per_million_output' => ['type' => 'string', 'default' => '0', 'label' => 'Cost per million output tokens ($)'],

            // Prompts live in settings so wording can be tuned by the business
            // without a deploy.
            'ai.vision_prompt' => ['type' => 'string', 'default' => 'You are cataloguing a piece of antique, estate or fine jewelry from photographs for a specialist dealer. Identify only what the images actually support, and say so when uncertain.', 'label' => 'Photo analysis instructions'],
            'ai.description_prompt' => ['type' => 'string', 'default' => 'Write for a specialist estate jewelry dealer. Be precise and restrained: no invented provenance, no superlatives, no claims the attributes do not support.', 'label' => 'Description instructions'],
        ];
    }

    public static function security(): array
    {
        return [
            'security.mfa_required_roles' => ['type' => 'json', 'default' => ['super-admin'], 'label' => 'Roles requiring MFA'],
            'security.session_timeout_minutes' => ['type' => 'integer', 'default' => 120, 'label' => 'Session timeout (minutes)'],
            'security.password_policy' => ['type' => 'json', 'default' => [
                'min_length' => 12, 'require_uppercase' => true, 'require_number' => true,
                'require_symbol' => true, 'expires_days' => 0,
            ], 'label' => 'Password policy'],
            'security.audit_retention_days' => ['type' => 'integer', 'default' => 730, 'label' => 'Audit retention (days)'],
            // IRS recordkeeping is 7 years; financial entries are retained separately.
            'security.audit_retention_days_financial' => ['type' => 'integer', 'default' => 2557, 'label' => 'Financial audit retention (days)'],
            'security.stepup_challenge_enabled' => ['type' => 'boolean', 'default' => true, 'label' => 'Step-up challenge on high-risk actions'],
            'security.stepup_actions' => ['type' => 'json', 'default' => [
                'override.approve', 'payments.credentials', 'security.mfa_settings',
            ], 'label' => 'Actions requiring the step-up challenge'],
            'security.stepup_method' => ['type' => 'string', 'default' => 'symbols', 'label' => 'Step-up method', 'help' => 'symbols or totp — swappable without code changes.'],
        ];
    }

    public static function commission(): array
    {
        return [
            'commission.default_type' => ['type' => 'string', 'default' => 'sales_based', 'label' => 'Default commission type'],
            'commission.default_rate_percent' => ['type' => 'string', 'default' => '4.5', 'label' => 'Default rate (%)'],
            'commission.default_tiers' => ['type' => 'json', 'default' => [
                ['threshold' => 0, 'rate' => 4.5],
                ['threshold' => 50000, 'rate' => 5.5],
            ], 'label' => 'Default tiers'],
            'commission.payroll_export_column_mapping' => ['type' => 'json', 'default' => [
                'employee_id' => 'user.id', 'employee_name' => 'user.name',
                'period_end' => 'period_end', 'amount' => 'amount',
            ], 'label' => 'Payroll export column mapping'],
        ];
    }

    /**
     * The pricing factors and weight engine (requirement 7).
     *
     * Deliberately explainable rather than a single opaque number: each factor
     * is a rate the business maintains, so a suggested price can be shown as a
     * breakdown and defended to a customer.
     */
    public static function pricing(): array
    {
        return [
            'pricing.metal_rates_per_gram' => ['type' => 'json', 'default' => [
                '950 platinum' => 28.50, '900 platinum' => 27.00,
                '24k' => 82.00, '22k' => 75.00, '18k' => 61.50, '14k' => 47.80,
                '10k' => 34.20, '9k' => 30.70,
                'sterling silver' => 0.85, 'fine silver' => 0.92,
                'palladium' => 31.60, 'nickel' => 0.02,
            ], 'label' => 'Metal value per gram', 'help' => 'Layer 1. Always available, and what every other layer falls back to.'],

            'pricing.gemstone_rates_per_carat' => ['type' => 'json', 'default' => [
                'diamond' => 2400.00, 'ruby' => 1800.00, 'sapphire' => 1100.00,
                'emerald' => 1500.00, 'pearl' => 120.00, 'garnet' => 90.00, 'opal' => 180.00,
            ], 'label' => 'Gemstone value per carat'],

            'pricing.brand_premiums' => ['type' => 'json', 'default' => [
                'cartier' => 1.35, 'van cleef & arpels' => 1.35, 'tiffany & co.' => 1.25,
                'bulgari' => 1.22, 'boucheron' => 1.18,
            ], 'label' => 'Maker multiplier', 'help' => 'Layer 3. Applied to the formula\'s retail price, not to the metal value.'],

            'pricing.period_premiums' => ['type' => 'json', 'default' => [
                'georgian' => 1.35, 'victorian' => 1.18, 'edwardian' => 1.20,
                'art deco' => 1.25, 'art nouveau' => 1.22, 'retro' => 1.08, 'mid-century' => 1.05,
            ], 'label' => 'Period multiplier', 'help' => 'Layer 3.'],

            'pricing.condition_adjustments' => ['type' => 'json', 'default' => [
                'excellent' => 1.05, 'very good' => 1.00, 'good' => 0.92,
                'fair' => 0.80, 'restored' => 0.88, 'damaged' => 0.65,
            ], 'label' => 'Condition multiplier', 'help' => 'Layer 3.'],

            // ---- The craftsman's formula (the foundation) -------------------
            // The formula never changes. These percentages do.
            'pricing.formula.overhead_percent' => ['type' => 'string', 'default' => '10', 'label' => 'Overhead cost (%)', 'help' => 'Step 2. Range 0–50.'],
            'pricing.formula.design_percent' => ['type' => 'string', 'default' => '5', 'label' => 'Design cost (%)', 'help' => 'Step 2. Range 0–50.'],
            'pricing.formula.wholesale_commission_percent' => ['type' => 'string', 'default' => '10', 'label' => 'Wholesale agent commission (%)', 'help' => 'Step 3. Range 0–30.'],
            'pricing.formula.retail_commission_percent' => ['type' => 'string', 'default' => '50', 'label' => 'Retail agent commission (%)', 'help' => 'Step 4. Range 0–80.'],

            'pricing.formula.rounding_enabled' => ['type' => 'boolean', 'default' => true, 'label' => 'Apply retail rounding'],
            'pricing.formula.rounding_increment' => ['type' => 'string', 'default' => '0.50', 'label' => 'Round up to the nearest', 'help' => 'A retail price of $10.46 is not a selling price; $10.50 is.'],

            // Each step switches off independently, so a pricing strategy can
            // be tested without the rest of the formula moving.
            'pricing.formula.step2_enabled' => ['type' => 'boolean', 'default' => true, 'label' => 'Step 2 · Basic price'],
            'pricing.formula.step3_enabled' => ['type' => 'boolean', 'default' => true, 'label' => 'Step 3 · Wholesale price'],
            'pricing.formula.step4_enabled' => ['type' => 'boolean', 'default' => true, 'label' => 'Step 4 · Retail price'],

            'pricing.formula.category_overrides' => ['type' => 'json', 'default' => [
                'rings' => ['overhead' => 10, 'design' => 5, 'wholesale' => 10, 'retail' => 50],
                'necklaces' => ['overhead' => 12, 'design' => 5, 'wholesale' => 10, 'retail' => 45],
                "men's accessories" => ['overhead' => 8, 'design' => 3, 'wholesale' => 12, 'retail' => 55],
                'watches' => ['overhead' => 10, 'design' => 8, 'wholesale' => 10, 'retail' => 50],
            ], 'label' => 'Percentages per category', 'help' => 'Different categories carry different economics.'],

            'pricing.formula.default_labour_by_category' => ['type' => 'json', 'default' => [
                'rings' => 120.00, 'necklaces' => 95.00, 'bracelets' => 95.00,
                'earrings' => 80.00, 'brooches' => 85.00, 'watches' => 180.00,
            ], 'label' => 'Standard labour per category ($)', 'help' => 'Used when the item record carries no labour cost of its own.'],

            // ---- Layer switches ---------------------------------------------
            // Layer 1 (the base rate table above) is always available: it is
            // what every other layer falls back to.
            'pricing.layer.live_rates_enabled' => ['type' => 'boolean', 'default' => false, 'label' => 'Layer 2 · Live metal rates', 'help' => 'Off uses the base rate table. On prices metal at the market feed below.'],
            'pricing.layer.multipliers_enabled' => ['type' => 'boolean', 'default' => true, 'label' => 'Layer 3 · Maker, period and condition'],
            'pricing.layer.market_enabled' => ['type' => 'boolean', 'default' => false, 'label' => 'Layer 4 · Market adjustments', 'help' => 'Category demand, season and how long the piece has been in stock.'],

            // ---- Layer 2 connection (vendor-neutral, rule 3.3) --------------
            'pricing.live_rates_endpoint' => ['type' => 'string', 'default' => null, 'label' => 'Metal rate feed URL'],
            'pricing.live_rates_api_key' => ['type' => 'encrypted_string', 'default' => null, 'label' => 'Metal rate feed key'],
            'pricing.live_rates_path' => ['type' => 'string', 'default' => 'rates', 'label' => 'Where the rates sit in the response', 'help' => 'Dotted path, e.g. data.rates. Leave blank if the response is the rates themselves.'],
            'pricing.live_rates_quoted_per_ounce' => ['type' => 'boolean', 'default' => true, 'label' => 'Feed quotes per troy ounce'],
            'pricing.live_rates_provider' => ['type' => 'string', 'default' => null, 'label' => 'Feed provider', 'help' => 'Chosen from the providers table, so adding one is a record rather than a deploy.'],
            'pricing.live_rates_metals' => ['type' => 'json', 'default' => ['gold', 'silver', 'platinum', 'palladium'], 'label' => 'Metals to take from the feed'],
            'pricing.live_rates_failure_behaviour' => ['type' => 'string', 'default' => 'base', 'label' => 'If the feed is unavailable', 'help' => 'base = use your own table · last_known = use the last rate it returned · hold = refuse to price the metal.'],
            'pricing.live_rates_alert_after_failures' => ['type' => 'integer', 'default' => 3, 'label' => 'Alert after this many consecutive failures', 'help' => 'Zero never alerts. One message per run of failures, not one per failure.'],
            'pricing.live_rates_alert_recipients' => ['type' => 'string', 'default' => null, 'label' => 'Alert recipients', 'help' => 'Comma-separated email addresses.'],
            'pricing.live_rates_cache_seconds' => ['type' => 'integer', 'default' => 900, 'label' => 'Re-check the feed every (seconds)'],
            'pricing.live_rates_timeout_seconds' => ['type' => 'integer', 'default' => 10, 'label' => 'Feed timeout (seconds)'],
            'pricing.metal_purity_fractions' => ['type' => 'json', 'default' => [
                '24k' => 0.999, '22k' => 0.917, '18k' => 0.75, '14k' => 0.585,
                '10k' => 0.417, '9k' => 0.375,
                '950 platinum' => 0.95, '900 platinum' => 0.90,
                'sterling silver' => 0.925, 'fine silver' => 0.999,
            ], 'label' => 'Purity of each alloy', 'help' => 'A feed quotes fine metal; a piece is rarely fine metal.'],

            // ---- Layer 4 tables ---------------------------------------------
            'pricing.category_demand' => ['type' => 'json', 'default' => [
                'bridal' => 1.08, 'rings' => 1.00, 'necklaces' => 1.00,
                'watches' => 1.05, 'brooches' => 0.95,
            ], 'label' => 'Category demand'],
            'pricing.seasonal_demand' => ['type' => 'json', 'default' => [
                'November' => 1.05, 'December' => 1.08, 'January' => 0.95, 'February' => 1.04,
            ], 'label' => 'Seasonal adjustment', 'help' => 'By month. Anything not listed is left alone.'],
            'pricing.regional_demand' => ['type' => 'json', 'default' => [
                'los angeles' => 1.05, 'new york' => 1.08, 'san francisco' => 1.06, 'online' => 1.00,
            ], 'label' => 'Regional adjustment'],
            'pricing.inventory_age_adjustments' => ['type' => 'json', 'default' => [
                '31' => 0.97, '61' => 0.93, '91' => 0.88, '120' => 0.80,
            ], 'label' => 'Inventory age adjustment', 'help' => 'Days in stock → multiplier. A piece that has not sold is telling you something.'],
            'pricing.min_rate_confidence' => ['type' => 'integer', 'default' => 0, 'label' => 'Flag rates below this confidence (%)', 'help' => 'Zero means never flag. A rate recorded as weak is marked "worth checking" in the working.'],
            'pricing.multiplier_cap_percent' => ['type' => 'string', 'default' => '0', 'label' => 'Cap maker/period/condition at (±%)', 'help' => 'Zero means no cap. Three multipliers compound: a Georgian signed piece in mint condition reaches ×3.7 before the market layer is even reached.'],
            'pricing.market_adjustment_cap_percent' => ['type' => 'string', 'default' => '30', 'label' => 'Cap market adjustments at (±%)', 'help' => 'Four multipliers compounding can run away. This is what stops one table edit moving the whole catalogue.'],

            // Stone grading, from the appraiser's rate table.
            'pricing.diamond_cut_rates' => ['type' => 'json', 'default' => [], 'label' => 'Diamond rate by cut', 'help' => 'In estate work the cut is often worth more than the size, so these replace the size band.'],
            'pricing.diamond_clarity_adjustments' => ['type' => 'json', 'default' => [], 'label' => 'Clarity adjustment'],
            'pricing.diamond_color_adjustments' => ['type' => 'json', 'default' => [], 'label' => 'Colour adjustment'],
            'pricing.diamond_cut_quality_adjustments' => ['type' => 'json', 'default' => [], 'label' => 'Cut grade adjustment'],
            'pricing.stone_treatment_adjustments' => ['type' => 'json', 'default' => [], 'label' => 'Treatment adjustment', 'help' => 'Heated, oiled and fracture-filled stones are worth materially less, and the disclosure is not optional.'],

            // A brooch is negotiated harder than a ring, and insured higher.
            'pricing.negotiation_floor_by_category' => ['type' => 'json', 'default' => [], 'label' => 'Negotiation floor per category (% of retail)'],
            'pricing.insurance_by_category' => ['type' => 'json', 'default' => [], 'label' => 'Insurance multiplier per category'],

            'pricing.suggestion_band_percent' => ['type' => 'integer', 'default' => 15, 'label' => 'Suggestion band (±%)'],
            'pricing.insurance_multiplier' => ['type' => 'string', 'default' => '1.15', 'label' => 'Insurance value multiplier'],
            'pricing.negotiation_floor_percent' => ['type' => 'integer', 'default' => 85, 'label' => 'Negotiation floor (% of retail)'],
        ];
    }

    /**
     * The quality gate.
     *
     * Which checks block a sale is a business decision, so the ranking of
     * every check is editable here rather than fixed in code (rule 3.1).
     */
    public static function qc(): array
    {
        return [
            'qc.enabled' => ['type' => 'boolean', 'default' => true, 'label' => 'Quality gauge'],
            'qc.minimum_photos' => ['type' => 'integer', 'default' => 5, 'label' => 'Photographs required'],
            'qc.show_customer_panel' => ['type' => 'boolean', 'default' => true, 'label' => 'Show verified facts to customers', 'help' => 'Specific attributable claims. Never a score or a star rating.'],
            'qc.check_types' => ['type' => 'json', 'default' => [], 'label' => 'Check ranking overrides', 'help' => 'check key → critical, standard, optional or disabled.'],
            'qc.block_listing_when_critical_fails' => ['type' => 'boolean', 'default' => true, 'label' => 'A failed critical check blocks listing'],
        ];
    }

    /** Phase 2 gates. All OFF by default (rule 3.4). */
    public static function features(): array
    {
        return [
            'features.rental.enabled' => ['type' => 'boolean', 'default' => false, 'label' => 'Rental services', 'help' => 'Short-term hire with deposit handling and return inspection.'],
            'features.salesperson_storefront.enabled' => ['type' => 'boolean', 'default' => false, 'label' => 'Salesperson storefronts', 'help' => 'Personal shopfronts with attributed commission on referred sales.'],
            'features.audit.quarterly_enabled' => ['type' => 'boolean', 'default' => false, 'label' => 'Quarterly audit', 'help' => 'Scheduled physical-count reconciliation with variance reporting.'],
        ];
    }
}
