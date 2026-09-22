{{-- Full-page POS layout for Livewire components. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Register' }} · Jewelry Trader</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="surface-pos text-ivory">
    <div class="flex min-h-screen flex-col">
        <header class="flex flex-wrap items-center justify-between gap-14 border-b border-hairline-dark px-22 py-14">
            <div>
                <div class="font-serif text-title-lg font-semibold text-gold">Jewelry Trader</div>
                <div class="mt-3 text-caption text-navy-eyebrow">
                    {{ auth()->user()?->location?->name ?? 'Register' }} · {{ auth()->user()?->name }}
                </div>
            </div>
            <div class="flex flex-wrap gap-9">
                <x-ui.tab :href="route('pos.sale')" :active="request()->routeIs('pos.sale')">Sale</x-ui.tab>
                <x-ui.tab :href="route('pos.returns')" :active="request()->routeIs('pos.returns')">Return</x-ui.tab>
                <x-ui.tab :href="route('admin.dashboard')">Admin</x-ui.tab>
            </div>
        </header>

        <div class="flex-1 px-22 py-18">{{ $slot }}</div>
    </div>
</body>
</html>
