@php $current = request()->route()?->getName(); @endphp
<nav class="flex w-full shrink-0 flex-col gap-22 bg-navy px-14 py-20 lg:min-h-screen lg:max-w-sidebar">
    <div>
        <div class="font-serif text-card-title font-semibold uppercase tracking-brand text-ivory">Jewelry</div>
        <div class="font-serif text-card-title font-semibold uppercase tracking-brand text-gold">Trader</div>
        <div class="mt-6 text-tiny uppercase tracking-eyebrow-lg text-navy-eyebrow">Estate &amp; Fine Jewelry</div>
    </div>

    <div class="flex flex-col gap-3">
        <x-ui.eyebrow tone="navy" class="mt-8 mb-4 pl-12">Operations</x-ui.eyebrow>
        <x-ui.nav-item :href="route('admin.dashboard')" :active="$current === 'admin.dashboard'">Dashboard</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.inventory')" :active="$current === 'admin.inventory'">Inventory</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.inventory.create')" :active="$current === 'admin.inventory.create'">Add / Edit Item</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.review')" :active="$current === 'admin.review'" badge="7">Review Queue</x-ui.nav-item>

        <x-ui.eyebrow tone="navy" class="mt-14 mb-4 pl-12">Administration</x-ui.eyebrow>
        <x-ui.nav-item :href="route('admin.settings')" :active="$current === 'admin.settings'">Settings Hub</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.settings.payments')" :active="$current === 'admin.settings.payments'">Payment Settings</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.settings.ai')" :active="$current === 'admin.settings.ai'">AI &amp; Automation</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.settings.security')" :active="$current === 'admin.settings.security'">Security</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.settings.flags')" :active="$current === 'admin.settings.flags'">Feature Flags</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.override')" :active="$current === 'admin.override'">Override &amp; Lock</x-ui.nav-item>

        <x-ui.eyebrow tone="navy" class="mt-14 mb-4 pl-12">Reporting</x-ui.eyebrow>
        <x-ui.nav-item :href="route('admin.commission')" :active="$current === 'admin.commission'">Commission Report</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.audit')" :active="$current === 'admin.audit'">Audit Log</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.roles')" :active="$current === 'admin.roles'">Roles &amp; Permissions</x-ui.nav-item>
    </div>

    <div class="mt-auto border-t border-hairline-dark pt-14">
        @auth
            <div class="flex items-center gap-10">
                <div class="flex size-38 items-center justify-center rounded-surface border border-hairline-panel font-serif text-meta text-gold">
                    {{ str(auth()->user()->name)->substr(0, 1) }}{{ str(auth()->user()->name)->after(' ')->substr(0, 1) }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-body-sm font-semibold text-ivory">{{ auth()->user()->name }}</div>
                    <div class="text-caption text-navy-eyebrow">{{ auth()->user()->getRoleNames()->map(fn ($r) => str($r)->headline())->implode(', ') ?: 'No role' }}</div>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-12">
                @csrf
                <button type="submit" class="cursor-pointer text-caption text-navy-eyebrow hover:text-gold">Sign out</button>
            </form>
        @endauth
    </div>
</nav>
