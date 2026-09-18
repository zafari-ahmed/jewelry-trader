{{-- Layout for Livewire full-page components (they pass data, not slots). --}}
<x-layouts::admin :title="$title ?? null" :heading="$heading ?? null" :subheading="$subheading ?? null">
    {{ $slot }}
</x-layouts::admin>
