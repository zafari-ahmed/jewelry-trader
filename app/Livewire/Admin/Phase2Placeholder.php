<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use Livewire\Component;

/**
 * Module 11: the nav item exists and explains itself while its flag is off.
 * No business logic — turning the flag on is what Phase 2 builds behind.
 */
class Phase2Placeholder extends Component
{
    public string $feature;

    /** Flag, title, what it will do, and the tables already waiting for it. */
    public const FEATURES = [
        'rental' => [
            'flag' => 'features.rental.enabled',
            'title' => 'Rental Services',
            'description' => 'Short-term hire of showcase pieces, with deposit handling, deductible options and return inspection.',
            'tables' => ['rental_agreements', 'rental_claims'],
            'seams' => ['The inventory_locks enum already carries a "rental" lock type, so no migration is needed when this is switched on.'],
        ],
        'salesperson_storefront' => [
            'flag' => 'features.salesperson_storefront.enabled',
            'title' => 'Salesperson Storefronts',
            'description' => 'A personal shopfront per salesperson, with its own branding and commission attributed on referred sales.',
            'tables' => ['salesperson_storefronts', 'storefront_inventory_requests'],
            'seams' => ['Commission plans already support split attribution, which is how a referred sale will be shared.'],
        ],
        'quarterly_audit' => [
            'flag' => 'features.audit.quarterly_enabled',
            'title' => 'Quarterly Audit',
            'description' => 'Scheduled physical-count reconciliation per location, with variance reporting and exception tracking.',
            'tables' => ['audit_reports', 'audit_exceptions'],
            'seams' => ['Every stock movement is already audit-logged, which is the data a reconciliation reads.'],
        ],
    ];

    public function mount(string $feature): void
    {
        abort_unless(isset(self::FEATURES[$feature]), 404);

        $this->feature = $feature;
    }

    public function render()
    {
        $meta = self::FEATURES[$this->feature];

        return view('livewire.admin.phase-2-placeholder', [
            'meta' => $meta,
            'enabled' => Setting::enabled($meta['flag']),
        ])->layout('layouts.admin-livewire', [
            'title' => $meta['title'],
            'heading' => $meta['title'],
            'subheading' => 'Coming in Phase 2',
        ]);
    }
}
