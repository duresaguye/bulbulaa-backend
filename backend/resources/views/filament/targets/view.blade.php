@php
    $details = $this->targetDetails();
    $target = $details['target'];
    $summary = $details['summary'];
    $format = fn ($value) => $target->formatValue($value);
    $gap = $target->allocationGap();
@endphp

<x-filament-panels::page>
    <div class="rt-stack">
        <section class="rt-figure" aria-label="{{ __('Progress against the annual target') }}">
            <dl class="rt-figure__numbers">
                <div>
                    <dt>{{ __('Annual target') }}</dt>
                    <dd>{{ $format($summary['target']) }} <span class="rt-unit">{{ $target->measurement_unit }}</span></dd>
                </div>
                <div>
                    <dt>{{ __('Reported to date') }}</dt>
                    <dd>{{ $format($summary['actual']) }}</dd>
                </div>
                @if ($target->aggregation->isAdditive())
                    <div>
                        <dt>{{ __('Remaining') }}</dt>
                        <dd>{{ $format($summary['remaining']) }}</dd>
                    </div>
                @endif
                <div>
                    <dt>{{ __('Achieved') }}</dt>
                    <dd>{{ $summary['achievement'] === null ? '—' : $summary['achievement'].'%' }}</dd>
                </div>
                <div>
                    <dt>{{ __('Pace') }}</dt>
                    <dd>
                        <x-filament::badge :color="\App\Services\TargetProgress::paceColor($summary['status'])">
                            {{ \App\Services\TargetProgress::paceLabel($summary['status']) }}
                        </x-filament::badge>
                    </dd>
                </div>
            </dl>
            <x-rt.pace-bar :summary="$summary" :label="$target->title" />
            <p class="rt-muted rt-small">
                @if ($summary['expected'] !== null)
                    {{ __('The tick marks the :value expected by the end of :month.', ['value' => $format($summary['expected']), 'month' => \App\Services\EthiopianCalendar::monthName(max(1, $details['asOfMonth']))]) }}
                @else
                    {{ __('Nothing was expected yet according to the monthly allocation.') }}
                @endif
                {{ $target->aggregation->getLabel() }}: {{ $target->aggregation->getDescription() }}
                {{ __('Reported figures include reports awaiting review. Annual reports use approved figures only.') }}
            </p>
        </section>

        <x-filament::section :heading="__('Monthly targets and results')">
            @if ($target->monthlyPlans->isEmpty())
                <p class="rt-muted">{{ __('This target has not been split across months yet. The responsible office allocates it from the button above.') }}</p>
            @else
                @if ($gap !== null && $gap != 0)
                    <x-filament::callout color="warning" :heading="__('Monthly targets do not add up to the annual target')">
                        <x-slot name="description">
                            {{ $gap > 0
                                ? __('Months total :total against an annual target of :target (:gap unallocated).', ['total' => $format($target->allocatedTotal()), 'target' => $format($target->target_value), 'gap' => $format($gap)])
                                : __('Months total :total against an annual target of :target (:gap over).', ['total' => $format($target->allocatedTotal()), 'target' => $format($target->target_value), 'gap' => $format(abs($gap))]) }}
                        </x-slot>
                    </x-filament::callout>
                @endif
                <div class="rt-table-wrap">
                    <table class="rt-table rt-table--cards">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Month') }}</th>
                                <th scope="col" class="rt-num">{{ __('Target') }}</th>
                                <th scope="col" class="rt-num">{{ __('Actual') }}</th>
                                <th scope="col" class="rt-num">{{ __('Achieved') }}</th>
                                <th scope="col">{{ __('Source') }}</th>
                            </tr>
                        </thead>
                        @foreach ($details['quarters'] as $quarter => $group)
                            <tbody>
                                @foreach ($group['rows'] as $row)
                                    <tr>
                                        <td>
                                            {{ $row['month'] }}
                                            @if (filled($row['activities']))
                                                <span class="rt-muted rt-block rt-small">{{ \Illuminate\Support\Str::limit($row['activities'], 120) }}</span>
                                            @endif
                                        </td>
                                        <td class="rt-num" data-label="{{ __('Target') }}">{{ $format($row['planned']) }}</td>
                                        <td class="rt-num" data-label="{{ __('Actual') }}">{{ $format($row['actual']) }}</td>
                                        <td class="rt-num" data-label="{{ __('Achieved') }}">{{ ($pct = \App\Services\TargetProgress::achievement($row['actual'], $row['planned'])) === null ? '—' : $pct.'%' }}</td>
                                        <td class="rt-muted" data-label="{{ __('Source') }}">{{ $row['source'] ?? 'Not reported' }}</td>
                                    </tr>
                                @endforeach
                                <tr class="rt-table__subtotal">
                                    <th scope="row">{{ __('Quarter :quarter', ['quarter' => $quarter]) }}</th>
                                    <td class="rt-num" data-label="{{ __('Target') }}">{{ $format($group['planned']) }}</td>
                                    <td class="rt-num" data-label="{{ __('Actual') }}">{{ $format($group['actual']) }}</td>
                                    <td class="rt-num" data-label="{{ __('Achieved') }}">{{ ($pct = \App\Services\TargetProgress::achievement($group['actual'], $group['planned'])) === null ? '—' : $pct.'%' }}</td>
                                    <td class="rt-empty"></td>
                                </tr>
                            </tbody>
                        @endforeach
                    </table>
                </div>
            @endif
        </x-filament::section>

        @if ($details['children']->isNotEmpty())
            <x-filament::section :heading="__('Distributed to offices')">
                @if ($target->aggregation->isAdditive() && $target->distributedTotal() != (float) $target->target_value)
                    <p class="rt-warning-text">
                        {{ __('Office targets total :total against :target.', ['total' => $format($target->distributedTotal()), 'target' => $format($target->target_value)]) }}
                    </p>
                @endif
                <div class="rt-table-wrap">
                    <table class="rt-table rt-table--cards">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Office') }}</th>
                                <th scope="col" class="rt-num">{{ __('Target') }}</th>
                                <th scope="col" class="rt-num">{{ __('Reported') }}</th>
                                <th scope="col">{{ __('Progress') }}</th>
                                <th scope="col">{{ __('Pace') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($details['children'] as $child)
                                <tr>
                                    <td>
                                        <a class="rt-link" href="{{ \App\Filament\Resources\AnnualTargets\AnnualTargetResource::getUrl('view', ['record' => $child['target']]) }}">
                                            {{ $child['target']->department->name }}
                                        </a>
                                    </td>
                                    <td class="rt-num" data-label="{{ __('Target') }}">{{ $format($child['target']->target_value) }}</td>
                                    <td class="rt-num" data-label="{{ __('Reported') }}">{{ $format($child['summary']['actual']) }}</td>
                                    <td class="rt-cell-bar" data-label="{{ __('Progress') }}"><x-rt.pace-bar :summary="$child['summary']" :label="$child['target']->department->name" /></td>
                                    <td data-label="{{ __('Pace') }}">
                                        <x-filament::badge :color="\App\Services\TargetProgress::paceColor($child['summary']['status'])">
                                            {{ \App\Services\TargetProgress::paceLabel($child['summary']['status']) }}
                                        </x-filament::badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section :heading="__('Assignment and revisions')">
            <dl class="rt-meta">
                <div><dt>{{ __('Assigned by') }}</dt><dd>{{ $target->assignedBy?->name ?? __('Not recorded') }}, {{ $target->created_at ? \App\Services\EthiopianCalendar::display($target->created_at) : '' }}</dd></div>
                <div><dt>{{ __('Originally assigned') }}</dt><dd>{{ $format($target->original_target_value) }} {{ $target->measurement_unit }}</dd></div>
                <div><dt>{{ __('Current target') }}</dt><dd>{{ $format($target->target_value) }} {{ $target->measurement_unit }}</dd></div>
            </dl>
            @if ($target->revisions->isNotEmpty())
                <ol class="rt-timeline">
                    @foreach ($target->revisions as $revision)
                        <li>
                            <span class="rt-strong">{{ __(':from to :to', ['from' => $format($revision->previous_value), 'to' => $format($revision->new_value)]) }}</span>
                            <span>{{ $revision->revisedBy?->name ?? __('Unknown user') }}</span>
                            <time class="rt-muted" datetime="{{ $revision->created_at->toIso8601String() }}">{{ \App\Services\EthiopianCalendar::display($revision->created_at, withTime: true) }}</time>
                            <p class="rt-timeline__comment">{{ $revision->reason }}</p>
                        </li>
                    @endforeach
                </ol>
            @else
                <p class="rt-muted rt-small">{{ __('No revisions. The target is as originally assigned.') }}</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
