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
                '24k' => 82.00, '22k' => 75.00, '18k' => 61.50, '14k' => 47.80, '9k' => 30.70,
                'sterling silver' => 0.85,
            ], 'label' => 'Metal value per gram', 'help' => 'Scrap or melt value used as the floor of a suggestion.'],

            'pricing.gemstone_rates_per_carat' => ['type' => 'json', 'default' => [
                'diamond' => 2400.00, 'ruby' => 1800.00, 'sapphire' => 1100.00,
                'emerald' => 1500.00, 'pearl' => 120.00, 'garnet' => 90.00, 'opal' => 180.00,
            ], 'label' => 'Gemstone value per carat'],

            'pricing.brand_premiums' => ['type' => 'json', 'default' => [
                'cartier' => 2.60, 'van cleef & arpels' => 2.60, 'tiffany & co.' => 1.90,
                'bulgari' => 1.80, 'boucheron' => 1.70,
            ], 'label' => 'Brand multiplier'],

            'pricing.period_premiums' => ['type' => 'json', 'default' => [
                'georgian' => 1.60, 'victorian' => 1.30, 'edwardian' => 1.35,
                'art deco' => 1.45, 'art nouveau' => 1.40, 'retro' => 1.15, 'mid-century' => 1.10,
            ], 'label' => 'Period multiplier'],

            'pricing.condition_adjustments' => ['type' => 'json', 'default' => [
                'excellent' => 1.00, 'very good' => 0.92, 'good' => 0.82,
                'fair' => 0.65, 'restored' => 0.75, 'damaged' => 0.45,
            ], 'label' => 'Condition multiplier'],

            'pricing.retail_multiplier' => ['type' => 'string', 'default' => '2.4', 'label' => 'Retail multiplier', 'help' => 'Applied to intrinsic value to reach a retail asking price.'],
            'pricing.suggestion_band_percent' => ['type' => 'integer', 'default' => 15, 'label' => 'Suggestion band (±%)'],
            'pricing.insurance_multiplier' => ['type' => 'string', 'default' => '1.15', 'label' => 'Insurance value multiplier'],
            'pricing.negotiation_floor_percent' => ['type' => 'integer', 'default' => 85, 'label' => 'Negotiation floor (% of retail)'],
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
