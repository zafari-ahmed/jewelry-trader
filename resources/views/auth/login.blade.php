<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Jewelry Trader</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- The design system has no login screen; built from its tokens and components. --}}
<body class="surface-pos text-ivory">
    <div class="flex min-h-screen items-center justify-center px-20">
        <div class="w-full max-w-cart">
            <div class="text-center">
                <div class="font-serif text-card-title font-semibold uppercase tracking-brand text-ivory">Jewelry</div>
                <div class="font-serif text-card-title font-semibold uppercase tracking-brand text-gold">Trader</div>
                <div class="mt-6 text-tiny uppercase tracking-eyebrow-lg text-navy-eyebrow">Estate &amp; Fine Jewelry</div>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="mt-22 rounded-surface border border-hairline-panel bg-navy-panel px-20 py-18">
                @csrf
                <div class="border-b border-hairline-dark pb-11 text-eyebrow uppercase tracking-eyebrow text-navy-eyebrow">Staff sign in</div>

                <div class="mt-15 flex flex-col gap-15">
                    <div>
                        <label for="email" class="mb-5 block text-label font-semibold text-ivory">Email</label>
                        <x-ui.input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus tone="dark" />
                    </div>

                    <div>
                        <label for="password" class="mb-5 block text-label font-semibold text-ivory">Password</label>
                        <x-ui.input id="password" name="password" type="password" required tone="dark" />
                    </div>

                    @error('email')
                        <div class="rounded-surface border border-status-required bg-navy px-11 py-9 text-caption text-status-required">{{ $message }}</div>
                    @enderror

                    <label class="flex items-center gap-8 text-body-sm text-navy-text">
                        <input type="checkbox" name="remember" class="size-15 accent-gold" />Remember this register
                    </label>

                    <x-ui.button type="submit" variant="pos-primary" size="lg" class="w-full">Sign in</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
