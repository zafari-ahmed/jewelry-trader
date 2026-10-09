@props([
    'table',
    'label',
    'rows' => [],
])

{{--
    One rate table, with its provenance.

    A multiplier nobody can source is an opinion wearing a decimal point, so
    each row carries the figure, how confident the business is in it, and
    where it came from. Confidence may be left blank — an honest blank is
    better than a number nobody stands behind.
--}}
<div>
    <x-ui.eyebrow class="mb-8">{{ $label }}</x-ui.eyebrow>

    <div class="overflow-x-auto">
        <table class="w-full text-body-sm">
            <thead>
                <tr class="text-label uppercase tracking-brand text-muted">
                    <th class="py-5 pr-10 text-left font-semibold">Entry</th>
                    <th class="py-5 pr-8 text-right font-semibold">Rate</th>
                    <th class="py-5 pr-8 text-right font-semibold">Confidence</th>
                    <th class="py-5 text-left font-semibold">Source</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $name => $row)
                    <tr class="border-t border-rule" wire:key="{{ $table }}-{{ $loop->index }}">
                        <td class="py-5 pr-10 align-middle">{{ str($name)->headline() }}</td>
                        <td class="py-4 pr-8">
                            <x-ui.input wire:model="state.{{ $table }}.{{ $name }}.multiplier" class="w-90 text-right" />
                        </td>
                        <td class="py-4 pr-8">
                            <x-ui.input wire:model="state.{{ $table }}.{{ $name }}.confidence" class="w-80 text-right" placeholder="—" />
                        </td>
                        <td class="py-4">
                            <x-ui.input wire:model="state.{{ $table }}.{{ $name }}.source" class="w-full" placeholder="Where this came from" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
