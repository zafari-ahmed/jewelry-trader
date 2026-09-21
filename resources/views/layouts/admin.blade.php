<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Admin' }} · Jewelry Trader</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ivory text-ink">
    <div class="flex min-h-screen flex-col lg:flex-row">
        @include('partials.admin-sidebar')

        <main class="flex-1 min-w-0">
            <header class="flex flex-wrap items-center justify-between gap-16 border-b border-hairline px-28 py-18">
                <div>
                    <h1 class="font-serif text-page-title font-semibold tracking-heading">{{ $heading ?? $title ?? 'Dashboard' }}</h1>
                    @isset($subheading)
                        <div class="mt-4 text-meta text-muted">{{ $subheading }}</div>
                    @endisset
                </div>
                <div class="flex items-center gap-9">
                    <x-ui.input placeholder="Search SKU, title, or maker…" class="w-230" />
                    @can('create', App\Models\Product::class)
                        <x-ui.button variant="primary" href="{{ route('admin.inventory.create') }}" class="shrink-0 whitespace-nowrap">New Item</x-ui.button>
                    @endcan
                </div>
            </header>

            <div class="px-28 py-22">
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
