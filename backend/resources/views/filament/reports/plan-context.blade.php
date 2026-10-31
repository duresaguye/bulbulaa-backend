@php
    /** @var \App\Models\AnnualTarget $target */
    $additive = $target->aggregation->isAdditive();
    $planned = (float) $plannedValue;
    $done = $reported === null ? null : (float) $reported;
    $remaining = $additive && $done !== null ? max(0, $planned - $done) : null;
    $reportedLabel = match ($target->aggregation) {
        \App\Enums\Aggregation::Average => 'Average so far',
        \App\Enums\Aggregation::Latest => 'Latest reported',
        default => $reportedLabel,
    };
    $percent = $additive && $planned > 0 && $done !== null ? min(100, round($done / $planned * 100)) : null;
@endphp

<div class="rt-plan">
    <dl class="rt-plan__figures">
        <div>
            <dt>{{ __('Month target') }}</dt>
            <dd>{{ $target->formatValue($planned) }} <span class="rt-unit">{{ $target->measurement_unit }}</span></dd>
        </div>
        <div>
            <dt>{{ __($reportedLabel) }}</dt>
            <dd>{{ $done === null ? __('None yet') : $target->formatValue($done) }}</dd>
        </div>
        @if ($remaining !== null)
            <div>
                <dt>{{ __('Still to do') }}</dt>
                <dd>{{ $target->formatValue($remaining) }}</dd>
            </div>
        @endif
    </dl>
    @if ($percent !== null)
        <div class="rt-plan__bar" role="img" aria-label="{{ __(':percent% of the month target reported so far', ['percent' => $percent]) }}">
            <span style="width: {{ $percent }}%"></span>
        </div>
    @endif
    @if (filled($activities))
        <p class="rt-plan__activities"><span class="rt-muted">{{ __('Planned:') }}</span> {{ \Illuminate\Support\Str::limit($activities, 160) }}</p>
    @endif
</div>
