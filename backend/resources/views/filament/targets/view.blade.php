@php
    $details = $this->targetDetails();
    $target = $details['target'];
    $summary = $details['summary'];
    $format = fn ($value) => $target->formatValue($value);
    $gap = $target->allocationGap();
@endphp

<x-filament-panels::page>
    <div class="rt-stack">
        <section class="rt-figure" aria-label="Progress against the annual target">
            <dl class="rt-figure__numbers">
                <div>
                    <dt>Annual target</dt>
                    <dd>{{ $format($summary['target']) }} <span class="rt-unit">{{ $target->measurement_unit }}</span></dd>
                </div>
                <div>
                    <dt>Reported to date</dt>
                    <dd>{{ $format($summary['actual']) }}</dd>
                </div>
                @if ($target->aggregation->isAdditive())
                    <div>
                        <dt>Remaining</dt>
                        <dd>{{ $format($summary['remaining']) }}</dd>
                    </div>
                @endif
                <div>
                    <dt>Achieved</dt>
                    <dd>{{ $summary['achievement'] === null ? '—' : $summary['achievement'].'%' }}</dd>
                </div>
                <div>
                    <dt>Pace</dt>
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
                    The tick marks the {{ $format($summary['expected']) }} expected by the end of {{ \App\Services\EthiopianCalendar::monthName(max(1, $details['asOfMonth'])) }}.
                @else
                    Nothing was expected yet according to the monthly allocation.
                @endif
                {{ $target->aggregation->getLabel() }}: {{ $target->aggregation->getDescription() }}
                Reported figures include reports awaiting review. Annual reports use approved figures only.
            </p>
        </section>

        <x-filament::section heading="Monthly targets and results">
            @if ($target->monthlyPlans->isEmpty())
                <p class="rt-muted">This target has not been split across months yet. The responsible office allocates it from the button above.</p>
            @else
                @if ($gap !== null && $gap != 0)
                    <x-filament::callout color="warning" heading="Monthly targets do not add up to the annual target">
                        <x-slot name="description">
                            Months total {{ $format($target->allocatedTotal()) }} against an annual target of {{ $format($target->target_value) }}
                            ({{ $gap > 0 ? $format($gap).' unallocated' : $format(abs($gap)).' over' }}).
                        </x-slot>
                    </x-filament::callout>
                @endif
                <div class="rt-table-wrap">
                    <table class="rt-table rt-table--cards">
                        <thead>
                            <tr>
                                <th scope="col">Month</th>
                                <th scope="col" class="rt-num">Target</th>
                                <th scope="col" class="rt-num">Actual</th>
                                <th scope="col" class="rt-num">Achieved</th>
                                <th scope="col">Source</th>
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
                                        <td class="rt-num" data-label="Target">{{ $format($row['planned']) }}</td>
                                        <td class="rt-num" data-label="Actual">{{ $format($row['actual']) }}</td>
                                        <td class="rt-num" data-label="Achieved">{{ ($pct = \App\Services\TargetProgress::achievement($row['actual'], $row['planned'])) === null ? '—' : $pct.'%' }}</td>
                                        <td class="rt-muted" data-label="Source">{{ $row['source'] ?? 'Not reported' }}</td>
                                    </tr>
                                @endforeach
                                <tr class="rt-table__subtotal">
                                    <th scope="row">Quarter {{ $quarter }}</th>
                                    <td class="rt-num" data-label="Target">{{ $format($group['planned']) }}</td>
                                    <td class="rt-num" data-label="Actual">{{ $format($group['actual']) }}</td>
                                    <td class="rt-num" data-label="Achieved">{{ ($pct = \App\Services\TargetProgress::achievement($group['actual'], $group['planned'])) === null ? '—' : $pct.'%' }}</td>
                                    <td class="rt-empty"></td>
                                </tr>
                            </tbody>
                        @endforeach
                    </table>
                </div>
            @endif
        </x-filament::section>

        @if ($details['children']->isNotEmpty())
            <x-filament::section heading="Distributed to offices">
                @if ($target->aggregation->isAdditive() && $target->distributedTotal() != (float) $target->target_value)
                    <p class="rt-warning-text">
                        Office targets total {{ $format($target->distributedTotal()) }} against {{ $format($target->target_value) }}.
                    </p>
                @endif
                <div class="rt-table-wrap">
                    <table class="rt-table rt-table--cards">
                        <thead>
                            <tr>
                                <th scope="col">Office</th>
                                <th scope="col" class="rt-num">Target</th>
                                <th scope="col" class="rt-num">Reported</th>
                                <th scope="col">Progress</th>
                                <th scope="col">Pace</th>
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
                                    <td class="rt-num" data-label="Target">{{ $format($child['target']->target_value) }}</td>
                                    <td class="rt-num" data-label="Reported">{{ $format($child['summary']['actual']) }}</td>
                                    <td class="rt-cell-bar" data-label="Progress"><x-rt.pace-bar :summary="$child['summary']" :label="$child['target']->department->name" /></td>
                                    <td data-label="Pace">
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

        <x-filament::section heading="Assignment and revisions">
            <dl class="rt-meta">
                <div><dt>Assigned by</dt><dd>{{ $target->assignedBy?->name ?? 'Not recorded' }}, {{ $target->created_at?->format('j M Y') }}</dd></div>
                <div><dt>Originally assigned</dt><dd>{{ $format($target->original_target_value) }} {{ $target->measurement_unit }}</dd></div>
                <div><dt>Current target</dt><dd>{{ $format($target->target_value) }} {{ $target->measurement_unit }}</dd></div>
            </dl>
            @if ($target->revisions->isNotEmpty())
                <ol class="rt-timeline">
                    @foreach ($target->revisions as $revision)
                        <li>
                            <span class="rt-strong">{{ $format($revision->previous_value) }} to {{ $format($revision->new_value) }}</span>
                            <span>{{ $revision->revisedBy?->name ?? 'Unknown user' }}</span>
                            <time class="rt-muted" datetime="{{ $revision->created_at->toIso8601String() }}">{{ $revision->created_at->format('j M Y, H:i') }}</time>
                            <p class="rt-timeline__comment">{{ $revision->reason }}</p>
                        </li>
                    @endforeach
                </ol>
            @else
                <p class="rt-muted rt-small">No revisions. The target is as originally assigned.</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
