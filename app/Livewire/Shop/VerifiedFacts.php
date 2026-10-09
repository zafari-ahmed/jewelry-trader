<?php

namespace App\Livewire\Shop;

use App\Models\AppraisalRequest;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Quality\QualityControlService;
use Livewire\Component;

/**
 * What a customer is told, and what they are deliberately not told.
 *
 * Specific verified facts, each attributed to a role and a date. No score and
 * no stars: the internal number measures how complete a record is, and a
 * customer would read it as a judgement about the piece. A piece in fair
 * condition with a complete record would show full marks — which is exactly
 * the sort of claim that gets a dealer into trouble.
 */
class VerifiedFacts extends Component
{
    public Product $product;

    public bool $requesting = false;

    public string $name = '';

    public string $email = '';

    public string $message = '';

    public ?string $sent = null;

    public function getClaimsProperty(): array
    {
        if (! Setting::get('qc.show_customer_panel', true)) {
            return [];
        }

        return app(QualityControlService::class)->publicClaims($this->product);
    }

    public function requestAppraisal(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        AppraisalRequest::create($data + [
            'product_id' => $this->product->id,
            'customer_id' => auth('customer')->id(),
            'status' => 'open',
        ]);

        $this->reset('name', 'email', 'message', 'requesting');

        $this->sent = 'Thank you — we have your request and will send the full record shortly.';
    }

    public function render()
    {
        return view('livewire.shop.verified-facts');
    }
}
