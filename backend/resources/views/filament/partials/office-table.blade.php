@php
    use App\Services\ReportingCalendar;

    $week = app(ReportingCalendar::class)->reportableWeek();
    $month = app(ReportingCalendar::class)->reportableMonth();
    $compact = $compact ?? false;
    $expandable = $expandable ?? false;
    $expanded = $expanded ?? null;
@endphp

<div class="rt-table-wrap">
    <table class="rt-table">
        <thead>
            <tr>
                <th scope="col">Office</th>
                <th scope="col">Targets</th>
                <th scope="col">Week {{ $week['week'] }}, {{ \App\Services\EthiopianCalendar::monthName($week['month']) }}</th>
                <th scope="col">{{ \App\Services\EthiopianCalendar::monthName($month['month']) }} report</th>
                <th scope="col" class="rt-num">Overdue</th>
                <th scope="col"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                @php($office = $row['department'])
                <tr>
                    <th scope="row" class="rt-office" style="--depth: {{ $compact ? 0 : $row['depth'] }}">
                        <span class="rt-strong">{{ $office->name }}</span>
                        @if ($compact && $office->parent)
                            <span class="rt-muted rt-block rt-small">Under {{ $office->parent->name }}</span>
                        @endif
                    </th>
                    <td>
                        @if ($row['targets']->isEmpty())
                            <span class="rt-muted">None assigned</span>
                        @else
                            <span class="rt-pace-counts">
                                @if ($row['counts']['on_track'])<span class="rt-count rt-count--success">{{ $row['counts']['on_track'] }} on track</span>@endif
                                @if ($row['counts']['at_risk'])<span class="rt-count rt-count--warning">{{ $row['counts']['at_risk'] }} at risk</span>@endif
                                @if ($row['counts']['behind'])<span class="rt-count rt-count--danger">{{ $row['counts']['behind'] }} behind</span>@endif
                                @if ($row['counts']['not_started'])<span class="rt-count">{{ $row['counts']['not_started'] }} not due</span>@endif
                            </span>
                        @endif
                    </td>
                    <td>
                        @if ($row['week_state'] !== 'not_required')
                            <x-filament::badge :color="ReportingCalendar::stateColor($row['week_state'])">{{ ReportingCalendar::stateLabel($row['week_state']) }}</x-filament::badge>
                        @else
                            <span class="rt-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if ($row['month_state'] !== 'not_required')
                            <x-filament::badge :color="ReportingCalendar::stateColor($row['month_state'])">{{ ReportingCalendar::stateLabel($row['month_state']) }}</x-filament::badge>
                        @else
                            <span class="rt-muted">—</span>
                        @endif
                    </td>
                    <td class="rt-num {{ $row['overdue'] > 0 ? 'rt-danger-text' : '' }}">{{ $row['overdue'] }}</td>
                    <td class="rt-row-actions">
                        @if ($expandable && $row['targets']->isNotEmpty())
                            <x-filament::link tag="button" size="sm" color="gray" wire:click="toggle({{ $office->id }})" :aria-expanded="$expanded === $office->id ? 'true' : 'false'">
                                {{ $expanded === $office->id ? 'Hide targets' : 'Show targets' }}
                            </x-filament::link>
                        @endif
                        <x-filament::link :href="\App\Filament\Resources\AnnualTargets\AnnualTargetResource::getUrl('index', ['filters' => ['department_id' => ['value' => $office->id]]])" size="sm">
                            Targets
                        </x-filament::link>
                        @if ($row['awaiting_review'] > 0 && \App\Filament\Pages\ReviewQueue::canAccess())
                            <x-filament::link :href="\App\Filament\Pages\ReviewQueue::getUrl()" size="sm">
                                Review ({{ $row['awaiting_review'] }})
                            </x-filament::link>
                        @endif
                    </td>
                </tr>
                @if ($expandable && $expanded === $office->id)
                    <tr class="rt-table__detail">
                        <td colspan="6">
                            <table class="rt-table rt-table--nested">
                                <thead>
                                    <tr>
                                        <th scope="col">Target</th>
                                        <th scope="col" class="rt-num">Annual target</th>
                                        <th scope="col" class="rt-num">Expected by now</th>
                                        <th scope="col" class="rt-num">Reported</th>
                                        <th scope="col">Progress</th>
                                        <th scope="col">Pace</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($row['targets'] as $item)
                                        <tr>
                                            <td>
                                                <a class="rt-link" href="{{ \App\Filament\Resources\AnnualTargets\AnnualTargetResource::getUrl('view', ['record' => $item['target']]) }}">{{ $item['target']->title }}</a>
                                                <span class="rt-muted rt-block rt-small">{{ $item['target']->measurement_unit }}</span>
                                            </td>
                                            <td class="rt-num">{{ $item['target']->formatValue($item['summary']['target']) }}</td>
                                            <td class="rt-num">{{ $item['target']->formatValue($item['summary']['expected']) }}</td>
                                            <td class="rt-num">{{ $item['target']->formatValue($item['summary']['actual']) }}</td>
                                            <td class="rt-cell-bar"><x-rt.pace-bar :summary="$item['summary']" :label="$item['target']->title" /></td>
                                            <td>
                                                <x-filament::badge :color="\App\Services\TargetProgress::paceColor($item['summary']['status'])">{{ \App\Services\TargetProgress::paceLabel($item['summary']['status']) }}</x-filament::badge>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>
