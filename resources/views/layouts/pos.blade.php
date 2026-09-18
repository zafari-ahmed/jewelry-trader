<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Register' }} · Jewelry Trader</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- Full-screen register chrome: no admin sidebar, large targets, navy ground. --}}
<body class="surface-pos text-ivory">
    <div class="flex min-h-screen flex-col">
        @include('partials.pos-header')

        <div class="flex flex-1 flex-col gap-18 px-22 py-18 xl:flex-row">
            <section class="min-w-0 flex-1">{{ $slot }}</section>

            @isset($cart)
                <aside class="w-full shrink-0 xl:max-w-cart">{{ $cart }}</aside>
            @else
                <aside class="w-full shrink-0 xl:max-w-cart">@include('partials.pos-cart')</aside>
            @endisset
        </div>
    </div>
</body>
</html>
