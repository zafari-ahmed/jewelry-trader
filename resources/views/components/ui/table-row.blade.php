@props(['head' => false, 'cols'])
<div {{ $attributes->class([
    'grid items-center gap-14 px-18',
    $head
        ? 'border-b border-hairline py-11 text-eyebrow uppercase tracking-eyebrow text-muted'
        : 'border-b border-rule py-13 text-body-sm last:border-b-0 hover:bg-ivory-raised',
]) }} style="grid-template-columns: {{ $cols }}">
    {{ $slot }}
</div>
