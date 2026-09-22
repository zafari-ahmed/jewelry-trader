<?php

namespace App\Livewire\Settings;

use App\Models\Category;
use App\Models\FieldColorRule;
use App\Services\Inventory\FieldColorResolver;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Settings sub-page for the colour-coded field system. Editing here takes
 * effect on the next render — the rule cache is dropped on write, so no deploy
 * (Module 5 acceptance).
 */
class FieldRules extends Component
{
    public string $category = '';

    public ?int $editingId = null;

    public array $form = [
        'field_name' => '', 'category' => '', 'color' => 'green',
        'is_required' => false, 'section' => 'details', 'label' => '', 'help' => '',
    ];

    public ?string $flash = null;

    public function mount(): void
    {
        Gate::authorize('manage-settings');
        Gate::authorize('manage-field-rules');
    }

    public function edit(int $id): void
    {
        $rule = FieldColorRule::findOrFail($id);

        $this->editingId = $rule->id;
        $this->form = [
            'field_name' => $rule->field_name,
            'category' => $rule->category ?? '',
            'color' => $rule->color,
            'is_required' => $rule->is_required,
            'section' => $rule->section,
            'label' => $rule->label ?? '',
            'help' => $rule->help ?? '',
        ];
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'form']);
    }

    public function save(): void
    {
        Gate::authorize('manage-field-rules');

        $data = $this->validate([
            'form.field_name' => ['required', 'string', 'max:128', 'regex:/^[a-z][a-z0-9_]*$/'],
            'form.category' => ['nullable', 'string', 'exists:categories,slug'],
            'form.color' => ['required', 'in:red,yellow,green,gray,blue'],
            'form.is_required' => ['boolean'],
            'form.section' => ['required', 'string', 'max:64'],
            'form.label' => ['nullable', 'string', 'max:255'],
            'form.help' => ['nullable', 'string', 'max:255'],
        ], messages: ['form.field_name.regex' => 'Use a lowercase snake_case field name.'])['form'];

        $data['category'] = $data['category'] ?: null;
        // A gray field is not applicable, so it cannot also be required.
        $data['is_required'] = $data['color'] === 'gray' ? false : (bool) $data['is_required'];

        FieldColorRule::updateOrCreate(
            $this->editingId
                ? ['id' => $this->editingId]
                : ['model' => FieldColorResolver::MODEL, 'field_name' => $data['field_name'], 'category' => $data['category']],
            $data + ['model' => FieldColorResolver::MODEL],
        );

        $this->cancel();
        $this->flash = 'Field rules updated — the change is live immediately.';
    }

    public function delete(int $id): void
    {
        Gate::authorize('manage-field-rules');

        FieldColorRule::findOrFail($id)->delete();

        $this->flash = 'Rule removed.';
    }

    public function render()
    {
        $resolver = app(FieldColorResolver::class);

        return view('livewire.settings.field-rules', [
            'categories' => Category::query()->active()->orderBy('sort_order')->get(),
            // What the intake form will actually show for the chosen category.
            'effective' => $resolver->rulesFor($this->category ?: null),
            'rules' => FieldColorRule::query()->orderBy('sort_order')->get(),
        ])->layout('layouts.admin-livewire', [
            'title' => 'Field Rules',
            'heading' => 'Field Rules',
            'subheading' => 'Which fields are required, suggested or not applicable — per category',
        ]);
    }
}
