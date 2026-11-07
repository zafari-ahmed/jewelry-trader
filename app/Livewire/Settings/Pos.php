<?php

namespace App\Livewire\Settings;

/**
 * The register's own settings. The `pos` group has existed in the registry
 * since Module 6 with no page behind it, so the return window, the discount
 * threshold and the receipt footer were only ever changeable in code — rule
 * 3.1 says otherwise. The restocking fee joins them here.
 */
class Pos extends SettingsComponent
{
    protected function permission(): string
    {
        return 'manage-settings';
    }

    protected function group(): string
    {
        return 'pos';
    }

    protected function rules(): array
    {
        return [
            'state.return_window_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'state.max_staff_discount_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'state.restock_fee_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'state.restock_fee_cap_cents' => ['required', 'integer', 'min:0'],
            'state.return_policy_text' => ['required', 'string', 'max:2000'],
            'state.receipt_footer' => ['nullable', 'string', 'max:2000'],
            'state.restock_fee_waiver_reasons' => ['array', 'min:1'],
            'state.restock_fee_waiver_reasons.*' => ['required', 'string', 'max:120'],
        ];
    }

    public function addWaiverReason(): void
    {
        $this->state['restock_fee_waiver_reasons'][] = '';
    }

    public function removeWaiverReason(int $index): void
    {
        unset($this->state['restock_fee_waiver_reasons'][$index]);

        $this->state['restock_fee_waiver_reasons'] = array_values($this->state['restock_fee_waiver_reasons']);
    }

    public function render()
    {
        return view('livewire.settings.pos')->layout('layouts.admin-livewire', [
            'title' => 'Point of Sale Settings',
            'heading' => 'Point of Sale',
            'subheading' => 'Return window, discount threshold, restocking fee, receipt text',
        ]);
    }
}
