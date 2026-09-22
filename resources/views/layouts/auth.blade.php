<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Sign in' }} · Jewelry Trader</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="surface-pos text-ivory">
    <div class="flex min-h-screen items-center justify-center px-20">
        <div class="w-full max-w-cart">
            <div class="text-center">
                <div class="font-serif text-card-title font-semibold uppercase tracking-brand text-ivory">Jewelry</div>
                <div class="font-serif text-card-title font-semibold uppercase tracking-brand text-gold">Trader</div>
                <div class="mt-6 text-tiny uppercase tracking-eyebrow-lg text-navy-eyebrow">Estate &amp; Fine Jewelry</div>
            </div>

            <div class="mt-22">{{ $slot }}</div>
        </div>
    </div>
</body>
</html>
