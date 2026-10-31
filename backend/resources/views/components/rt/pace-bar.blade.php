@props([
    'summary',
    'label' => null,
])

{{--
    Target bar: the fill is the actual result, the tick is where the monthly allocation expected the office to be by now.
--}}
@php
    $target = $summary['target'] ?: 0;
    $actualShare = $target > 0 && $summary['actual'] !== null ? min(100, $summary['actual'] / $target * 100) : 0;
    $expectedShare = $target > 0 && $summary['expected'] !== null ? min(100, $summary['expected'] / $target * 100) : null;
    $status = $summary['status'];
@endphp

<div {{ $attributes->class(['rt-pace', "rt-pace--{$status}"]) }}
    role="img"
    aria-label="{{ $label ?? 'Progress' }}: {{ $summary['achievement'] === null ? 'no results reported' : $summary['achievement'].'% of the annual target' }}{{ $summary['expected'] !== null ? ', expected '.round($expectedShare).'% by now' : '' }}"
>
    <div class="rt-pace__track">
        <div class="rt-pace__fill" style="width: {{ round($actualShare, 1) }}%"></div>
        @if ($expectedShare !== null)
            <div class="rt-pace__tick" style="left: {{ round($expectedShare, 1) }}%"></div>
        @endif
    </div>
</div>
