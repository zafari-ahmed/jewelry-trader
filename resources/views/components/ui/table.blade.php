@props(['cols' => null])
{{-- Grid-based table: the design lays rows out on a shared column template. --}}
<div {{ $attributes->class(['overflow-x-auto rounded-surface border border-border-card bg-surface']) }}>
    <div class="min-w-storefront lg:min-w-0">{{ $slot }}</div>
</div>
