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
