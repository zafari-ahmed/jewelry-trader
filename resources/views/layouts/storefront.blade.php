<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Antique & Estate Jewelry' }} · Jewelry Trader</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ivory text-ink">
    <div class="flex min-h-screen flex-col">
        @include('partials.storefront-header')
        <main class="flex-1">{{ $slot }}</main>
        @include('partials.storefront-footer')
    </div>
</body>
</html>
