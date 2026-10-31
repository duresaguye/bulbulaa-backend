@php
    use App\Services\ReportingCalendar;

    $week = app(ReportingCalendar::class)->reportableWeek();
    $month = app(ReportingCalendar::class)->reportableMonth();
    $compact = $compact ?? false;
    $expandable = $expandable ?? false;
    $expanded = $expanded ?? null;
@endphp

<div class="rt-table-wrap">
    <table class="rt-table rt-table--cards">
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
                    <td data-label="Targets">
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
                    <td data-label="Week {{ $week['week'] }}, {{ \App\Services\EthiopianCalendar::monthName($week['month']) }}">
                        @if ($row['week_state'] !== 'not_required')
                            <x-filament::badge :color="ReportingCalendar::stateColor($row['week_state'])">{{ ReportingCalendar::stateLabel($row['week_state']) }}</x-filament::badge>
                        @else
                            <span class="rt-muted">—</span>
                        @endif
                    </td>
                    <td data-label="{{ \App\Services\EthiopianCalendar::monthName($month['month']) }} report">
                        @if ($row['month_state'] !== 'not_required')
                            <x-filament::badge :color="ReportingCalendar::stateColor($row['month_state'])">{{ ReportingCalendar::stateLabel($row['month_state']) }}</x-filament::badge>
                        @else
                            <span class="rt-muted">—</span>
                        @endif
                    </td>
                    <td data-label="Overdue" class="rt-num {{ $row['overdue'] > 0 ? 'rt-danger-text' : '' }}">{{ $row['overdue'] }}</td>
                    <td class="rt-row-actions">
                        @if ($row['awaiting_review'] > 0 && \App\Filament\Pages\ReviewQueue::canAccess())
                            <x-filament::button tag="a" :href="\App\Filament\Pages\ReviewQueue::getUrl()" size="xs">
                                Review {{ $row['awaiting_review'] }}
                            </x-filament::button>
                        @endif
                        @if ($expandable && $row['targets']->isNotEmpty())
                            <button type="button" class="rt-disclosure" wire:click="toggle({{ $office->id }})" aria-expanded="{{ $expanded === $office->id ? 'true' : 'false' }}">
                                <span>{{ $expanded === $office->id ? 'Hide targets' : 'Targets' }}</span>
                                <x-filament::icon icon="heroicon-m-chevron-down" class="rt-disclosure__icon" />
                            </button>
                        @elseif ($row['targets']->isNotEmpty() || ! $expandable)
                            <x-filament::link :href="\App\Filament\Resources\AnnualTargets\AnnualTargetResource::getUrl('index', ['filters' => ['department_id' => ['value' => $office->id]]])" size="sm">
                                Targets
                            </x-filament::link>
                        @endif
                    </td>
                </tr>
                @if ($expandable && $expanded === $office->id)
                    <tr class="rt-table__detail">
                        <td colspan="6">
                            <table class="rt-table rt-table--nested rt-table--cards">
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
                                            <td class="rt-card-title">
                                                <a class="rt-link" href="{{ \App\Filament\Resources\AnnualTargets\AnnualTargetResource::getUrl('view', ['record' => $item['target']]) }}">{{ $item['target']->title }}</a>
                                                <span class="rt-muted rt-block rt-small">{{ $item['target']->measurement_unit }}</span>
                                            </td>
                                            <td data-label="Annual target" class="rt-num">{{ $item['target']->formatValue($item['summary']['target']) }}</td>
                                            <td data-label="Expected by now" class="rt-num">{{ $item['target']->formatValue($item['summary']['expected']) }}</td>
                                            <td data-label="Reported" class="rt-num">{{ $item['target']->formatValue($item['summary']['actual']) }}</td>
                                            <td class="rt-cell-bar" data-label="Progress"><x-rt.pace-bar :summary="$item['summary']" :label="$item['target']->title" /></td>
                                            <td data-label="Pace">
                                                <x-filament::badge :color="\App\Services\TargetProgress::paceColor($item['summary']['status'])">{{ \App\Services\TargetProgress::paceLabel($item['summary']['status']) }}</x-filament::badge>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <p class="rt-detail-footer">
                                <x-filament::link :href="\App\Filament\Resources\AnnualTargets\AnnualTargetResource::getUrl('index', ['filters' => ['department_id' => ['value' => $office->id]]])" size="sm">
                                    Open {{ $office->name }} targets
                                </x-filament::link>
                            </p>
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>
