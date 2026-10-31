@php
    use App\Services\EthiopianCalendar;
    use App\Services\ReportingCalendar;
    use App\Services\TargetProgress;

    $mode = $this->mode();
    $actions = $mode === 'none' ? [] : $this->nextActions();
    $oversight = $this->overseesOffices();
    $stateIcon = [
        'overdue' => 'heroicon-o-exclamation-circle',
        'returned' => 'heroicon-o-arrow-uturn-left',
        'due' => 'heroicon-o-pencil-square',
        'draft' => 'heroicon-o-pencil-square',
        'submitted' => 'heroicon-o-clock',
        'approved' => 'heroicon-o-check-circle',
    ];
@endphp

<x-filament-panels::page>
    @if ($mode === 'none')
        <x-filament::section>
            <p>Your account has no planning or reporting responsibilities. Use the menu for the tasks you are allowed to do, or ask an administrator to assign you to an office.</p>
        </x-filament::section>
    @else
        <div class="rt-stack">
            <div class="rt-toolbar">
                <label class="rt-select">
                    <span>Year (E.C.)</span>
                    <select wire:model.live="year">
                        @foreach ($this->yearOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($oversight && count($this->unitOptions()) > 0)
                    <label class="rt-select">
                        <span>Department</span>
                        <select wire:model.live="unit">
                            <option value="">All offices</option>
                            @foreach ($this->unitOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }} and offices below</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <span wire:loading class="rt-muted rt-small">Updating…</span>
            </div>

            <section class="rt-actions" aria-labelledby="rt-actions-heading">
                <h2 id="rt-actions-heading" class="rt-h2">What needs doing</h2>
                @if (count($actions) === 0)
                    <p class="rt-muted">Nothing is due from you right now.</p>
                @else
                    <ul class="rt-actions__list">
                        @foreach ($actions as $action)
                            <li class="rt-actions__item rt-actions__item--{{ $action['state'] }}">
                                <x-filament::icon :icon="$stateIcon[$action['state']] ?? 'heroicon-o-document-text'" class="rt-actions__icon" />
                                <div class="rt-actions__text">
                                    <p class="rt-strong">{{ $action['title'] }}</p>
                                    <p class="rt-muted rt-small">{{ $action['detail'] }}</p>
                                </div>
                                @if ($action['url'] && $action['action'])
                                    <x-filament::button
                                        tag="a"
                                        :href="$action['url']"
                                        size="sm"
                                        :color="in_array($action['state'], ['due', 'overdue', 'returned', 'draft', 'submitted']) && $action['action'] !== 'View' ? 'primary' : 'gray'"
                                        :outlined="$action['action'] === 'View'"
                                    >
                                        {{ $action['action'] }}
                                    </x-filament::button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if ($oversight)
                @php($metrics = $this->metrics())
                <dl class="rt-metrics" aria-label="Organisation summary for {{ $this->year }}">
                    @foreach ($metrics as $key => $metric)
                        <div class="rt-metrics__item rt-metrics__item--{{ $key }}">
                            <dt>{{ $metric['label'] }}</dt>
                            <dd>{{ $metric['value'] }}</dd>
                            @if ($metric['detail'])
                                <dd class="rt-muted rt-small">{{ $metric['detail'] }}</dd>
                            @endif
                        </div>
                    @endforeach
                </dl>

                <x-filament::section heading="Offices needing attention">
                    <x-slot name="afterHeader">
                        <x-filament::link :href="\App\Filament\Pages\OfficePerformance::getUrl(['year' => $this->year])" size="sm">
                            All offices
                        </x-filament::link>
                    </x-slot>
                    @php($attention = $this->officesNeedingAttention())
                    @if ($attention->isEmpty())
                        <p class="rt-muted">No office is behind plan or overdue with reports.</p>
                    @else
                        @include('filament.partials.office-table', ['rows' => $attention, 'compact' => true])
                    @endif
                </x-filament::section>

                <div class="rt-columns">
                    <x-filament::section heading="Targets behind plan">
                        @php($atRisk = $this->targetsAtRisk())
                        @if ($atRisk->isEmpty())
                            <p class="rt-muted">No target is below its expected pace.</p>
                        @else
                            <ul class="rt-target-list">
                                @foreach ($atRisk as $item)
                                    <li>
                                        <a class="rt-link rt-strong" href="{{ \App\Filament\Resources\AnnualTargets\AnnualTargetResource::getUrl('view', ['record' => $item['target']]) }}">{{ $item['target']->title }}</a>
                                        <span class="rt-muted rt-small rt-block">
                                            {{ $item['office']->name }}. {{ $item['target']->formatValue($item['summary']['actual'] ?? 0) }} of {{ $item['target']->formatValue($item['summary']['expected']) }} expected by now.
                                        </span>
                                        <x-rt.pace-bar :summary="$item['summary']" :label="$item['target']->title" />
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-filament::section>

                    <x-filament::section heading="Recent submissions and decisions">
                        @php($activity = $this->recentActivity())
                        @if ($activity->isEmpty())
                            <p class="rt-muted">No reports have been submitted yet.</p>
                        @else
                            <ol class="rt-timeline">
                                @foreach ($activity as $review)
                                    <li>
                                        <x-filament::badge :color="$review->action->getColor()">{{ $review->action->getLabel() }}</x-filament::badge>
                                        @if ($url = $this::reviewableUrl($review))
                                            <a class="rt-link" href="{{ $url }}">{{ $this::reviewableLabel($review) }}</a>
                                        @else
                                            <span>{{ $this::reviewableLabel($review) }}</span>
                                        @endif
                                        <span class="rt-muted rt-small">{{ $review->user?->name }}, {{ $review->created_at->diffForHumans() }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </x-filament::section>
                </div>
            @endif

            @php($targets = $this->myOffices()->isNotEmpty() ? $this->myTargets() : collect())
            @if ($this->myOffices()->isNotEmpty() && ($targets->isNotEmpty() || ! $oversight))
                <x-filament::section :heading="$this->myOffices()->count() === 1 ? $this->myOffices()->first()->name.': targets for '.$this->year : 'Your offices\' targets for '.$this->year">
                    @if ($targets->isEmpty())
                        <p class="rt-muted">No annual targets are assigned to your office for {{ $this->year }}.</p>
                    @else
                        <div class="rt-table-wrap">
                            <table class="rt-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Target</th>
                                        <th scope="col" class="rt-num">This month</th>
                                        <th scope="col" class="rt-num">Annual target</th>
                                        <th scope="col" class="rt-num">Achieved so far</th>
                                        <th scope="col">Progress</th>
                                        <th scope="col">Pace</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($targets as $item)
                                        @php($target = $item['target'])
                                        <tr>
                                            <td>
                                                <a class="rt-link rt-strong" href="{{ \App\Filament\Resources\AnnualTargets\AnnualTargetResource::getUrl('view', ['record' => $target]) }}">{{ $target->title }}</a>
                                                <span class="rt-muted rt-block rt-small">{{ $target->measurement_unit }}@if ($this->myOffices()->count() > 1), {{ $target->department->name }}@endif</span>
                                            </td>
                                            <td class="rt-num">
                                                @if ($item['month_target'] === null)
                                                    <span class="rt-muted">—</span>
                                                @else
                                                    {{ $target->formatValue($item['month_actual'] ?? 0) }} of {{ $target->formatValue($item['month_target']) }}
                                                    <span class="rt-muted rt-block rt-small">{{ ($pct = TargetProgress::achievement($item['month_actual'] ?? 0, $item['month_target'])) === null ? '' : $pct.'%' }}</span>
                                                @endif
                                            </td>
                                            <td class="rt-num">{{ $target->formatValue($target->target_value) }}</td>
                                            <td class="rt-num">
                                                {{ $target->formatValue($item['summary']['actual']) }}
                                                <span class="rt-muted rt-block rt-small">{{ $item['summary']['achievement'] === null ? '' : $item['summary']['achievement'].'%' }}</span>
                                            </td>
                                            <td class="rt-cell-bar"><x-rt.pace-bar :summary="$item['summary']" :label="$target->title" /></td>
                                            <td>
                                                <x-filament::badge :color="TargetProgress::paceColor($item['summary']['status'])">{{ TargetProgress::paceLabel($item['summary']['status']) }}</x-filament::badge>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="rt-muted rt-small rt-legend">
                            <span class="rt-legend__fill" aria-hidden="true"></span> Achieved
                            <span class="rt-legend__tick" aria-hidden="true"></span> Expected by the end of last month
                        </p>
                    @endif
                </x-filament::section>

                @if (! $oversight)
                    @php($feedback = $this->recentFeedback())
                    @if ($feedback->isNotEmpty())
                        <x-filament::section heading="Recent feedback from reviewers">
                            <ol class="rt-timeline">
                                @foreach ($feedback as $review)
                                    <li>
                                        <x-filament::badge :color="$review->action->getColor()">{{ $review->action->getLabel() }}</x-filament::badge>
                                        @if ($url = $this::reviewableUrl($review))
                                            <a class="rt-link" href="{{ $url }}">{{ $this::reviewableLabel($review) }}</a>
                                        @endif
                                        <span class="rt-muted rt-small">{{ $review->user?->name }}, {{ $review->created_at->diffForHumans() }}</span>
                                        @if (filled($review->comment))
                                            <p class="rt-timeline__comment">{{ $review->comment }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </x-filament::section>
                    @endif
                @endif
            @endif
        </div>
    @endif
</x-filament-panels::page>
