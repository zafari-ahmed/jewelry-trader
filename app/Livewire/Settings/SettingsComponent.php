<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use App\Support\SettingsRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Shared behaviour for the settings pages: permission gate, load from the
 * settings table, persist through Setting::set (which audits and re-caches).
 */
abstract class SettingsComponent extends Component
{
    public array $state = [];

    public ?string $saved = null;

    /** Permission required to open and save this page. */
    abstract protected function permission(): string;

    /** Registry group backing this page, e.g. "general". */
    abstract protected function group(): string;

    /** Settings whose stored value must never be sent to the browser (rule 3.2). */
    protected function secretKeys(): array
    {
        return [];
    }

    public function mount(): void
    {
        // Rule 3.6: the check is here, not only on the nav item.
        Gate::authorize('manage-settings');
        Gate::authorize($this->permission());

        $this->loadState();
    }

    protected function loadState(): void
    {
        foreach (SettingsRegistry::group($this->group()) as $path => $meta) {
            $key = $this->shortKey($path);

            $this->state[$key] = in_array($key, $this->secretKeys(), true)
                ? ''                       // never round-trip a secret into the form
                : Setting::get($path, $meta['default']);
        }
    }

    public function save(): void
    {
        Gate::authorize('manage-settings');
        Gate::authorize($this->permission());

        if ($this->rules() !== []) {
            $this->validate($this->rules());
        }

        foreach (SettingsRegistry::group($this->group()) as $path => $meta) {
            $key = $this->shortKey($path);
            $value = $this->state[$key] ?? null;

            // An empty secret field means "leave the stored value alone".
            if (in_array($key, $this->secretKeys(), true) && ($value === '' || $value === null)) {
                continue;
            }

            Setting::set($path, $this->castForStorage($value, $meta['type']), Auth::id());
        }

        foreach ($this->secretKeys() as $key) {
            $this->state[$key] = '';
        }

        $this->saved = 'Saved.';
        $this->loadState();
    }

    protected function castForStorage(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            default => $value,
        };
    }

    /** Masked display value for a stored secret, or null when unset. */
    public function masked(string $key): ?string
    {
        return Setting::masked("{$this->group()}.{$key}");
    }

    protected function shortKey(string $path): string
    {
        return str_replace('.', '_', substr($path, strlen($this->group()) + 1));
    }

    protected function rules(): array
    {
        return [];
    }
}
