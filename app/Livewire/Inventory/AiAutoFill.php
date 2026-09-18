<?php

namespace App\Livewire\Inventory;

use App\Exceptions\FeatureNotEnabledException;
use App\Models\Setting;
use App\Services\AI\Contracts\AiVisionProvider;
use Livewire\Component;

/**
 * The only UI Module 2 is allowed to add: a button gated on the ai.vision flag.
 *
 * Off (Phase 1 default): disabled, with a tooltip explaining why.
 * On: clickable, and the call reaches the Null provider and is refused — that
 * is the correct Phase 1 behaviour, and it proves the seam is wired end to end.
 */
class AiAutoFill extends Component
{
    public ?string $message = null;

    public ?string $messageTone = null;

    public function getEnabledProperty(): bool
    {
        return Setting::enabled('ai.vision');
    }

    public function autoFill(): void
    {
        $this->reset('message', 'messageTone');

        // Flag check first: calling code should never reach a disabled capability.
        if (! $this->enabled) {
            $this->message = 'Enable AI in Settings to use this.';
            $this->messageTone = 'muted';

            return;
        }

        try {
            // Module 5 passes this product's uploaded photo paths.
            app(AiVisionProvider::class)->analyze([], 'classification');
        } catch (FeatureNotEnabledException $e) {
            $this->message = $e->getMessage();
            $this->messageTone = 'suggested';
        }
    }

    public function render()
    {
        return view('livewire.inventory.ai-auto-fill');
    }
}
