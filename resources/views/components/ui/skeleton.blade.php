@props(['rows' => 3])
<div {{ $attributes->class(['flex flex-col gap-10']) }}>
    @for ($i = 0; $i < $rows; $i++)
        <div class="h-15 rounded-surface bg-disabled-ground"></div>
    @endfor
</div>
