<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use App\Services\Quality\QualityCheckRegistry;
use Illuminate\Support\Facades\Auth;

/**
 * What the quality gate asks, and what it refuses to let past.
 *
 * Whether a missing hallmark blocks a sale is a business decision, so every
 * check can be re-ranked or switched off here rather than being fixed in the
 * registry (rule 3.1).
 */
class QualityControl extends SettingsComponent
{
    protected function permission(): string
    {
        return 'manage-settings';
    }

    protected function group(): string
    {
        return 'qc';
    }

    /**
     * Check rankings, keyed safely for form binding.
     *
     * Check keys read as "2.4" because that is how the specification numbers
     * them and how staff refer to them — but a dot in a form field name is
     * nesting, so "2.4" would bind as [2][4] and silently never save. The
     * dots become underscores for the round trip and are put back on save.
     */
    public array $ranks = [];

    protected function rules(): array
    {
        return [
            'state.minimum_photos' => ['required', 'integer', 'min:1', 'max:30'],
        ];
    }

    public static function fieldKey(string $checkKey): string
    {
        return str_replace('.', '_', $checkKey);
    }

    protected function loadState(): void
    {
        parent::loadState();

        $stored = Setting::get('qc.check_types', []);
        $stored = is_array($stored) ? $stored : [];

        $this->ranks = [];

        foreach (app(QualityCheckRegistry::class)->definitionsForSettings() as $check) {
            $this->ranks[self::fieldKey($check['key'])] = $stored[$check['key']] ?? '';
        }
    }

    public function save(): void
    {
        $lookup = collect(app(QualityCheckRegistry::class)->definitionsForSettings())
            ->keyBy(fn (array $check) => self::fieldKey($check['key']));

        $types = [];

        foreach ($this->ranks as $fieldKey => $type) {
            // An empty choice means "leave it at the default", which is an
            // absent row rather than a stored blank.
            if ($type === '' || $type === null || ! $lookup->has($fieldKey)) {
                continue;
            }

            $types[$lookup[$fieldKey]['key']] = $type;
        }

        $this->state['check_types'] = $types;

        parent::save();

        Setting::set('qc.check_types', $types, Auth::id());
        $this->loadState();
    }

    public function render()
    {
        $registry = app(QualityCheckRegistry::class);

        return view('livewire.settings.quality-control', [
            'checks' => collect($registry->definitionsForSettings())->groupBy('stage'),
        ])->layout('layouts.admin-livewire', [
            'title' => 'Quality Control',
            'heading' => 'Quality control',
            'subheading' => 'What the gate asks, and what it will not let past',
        ]);
    }
}
