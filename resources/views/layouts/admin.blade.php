<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Admin' }} · Jewelry Trader</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- x-data on the body so the mobile nav state is shared by the bar and the drawer. --}}
<body class="bg-ivory text-ink" x-data="{ nav: false }">
    {{-- Mobile top bar: the full sidebar is over 1,100px tall on a phone, so it
         collapses behind this and the work area starts at the top of the screen. --}}
    <div class="flex items-center justify-between gap-12 border-b border-hairline bg-navy px-16 py-12 lg:hidden">
        <a href="{{ route('admin.dashboard') }}" class="font-serif text-card-title font-semibold uppercase tracking-brand text-ivory">
            Jewelry <span class="text-gold">Trader</span>
        </a>
        <button type="button" @click="nav = !nav" aria-label="Menu"
            class="flex size-38 cursor-pointer items-center justify-center rounded-surface border border-navy-border text-ivory">
            <span x-show="!nav" class="text-title-lg">☰</span>
            <span x-show="nav" x-cloak class="text-title-lg">×</span>
        </button>
    </div>

    <div class="flex min-h-screen flex-col lg:flex-row">
        @include('partials.admin-sidebar')

        <main class="flex-1 min-w-0">
            <header class="flex flex-wrap items-center justify-between gap-16 border-b border-hairline px-16 py-14 lg:px-28 lg:py-18">
                <div>
                    <h1 class="font-serif text-page-title font-semibold tracking-heading">{{ $heading ?? $title ?? 'Dashboard' }}</h1>
                    @isset($subheading)
                        <div class="mt-4 text-meta text-muted">{{ $subheading }}</div>
                    @endisset
                </div>
                <div class="hidden items-center gap-9 lg:flex">
                    <x-ui.input placeholder="Search SKU, title, or maker…" class="w-230" />
                    @can('create', App\Models\Product::class)
                        <x-ui.button variant="primary" href="{{ route('admin.inventory.create') }}" class="shrink-0 whitespace-nowrap">New Item</x-ui.button>
                    @endcan
                </div>
            </header>

            <div class="px-16 py-16 lg:px-28 lg:py-22">
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
