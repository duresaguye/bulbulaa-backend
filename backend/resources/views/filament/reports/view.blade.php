@php
    $record = $this->getRecord();
    $status = $record->status;
    $results = $this->resultsTable();
    $evidence = $this->evidence();
    $reviews = $record->reviews()->with('user')->get();
    $latestReturn = $status === \App\Enums\ReportStatus::Returned ? $record->latestReturn : null;
@endphp

<x-filament-panels::page>
    <div class="rt-stack">
        <section class="rt-status rt-status--{{ $status->getColor() }}" aria-label="{{ __('Report status') }}">
            <div class="rt-status__main">
                <x-filament::badge :color="$status->getColor()" size="lg">{{ $status->getLabel() }}</x-filament::badge>
                <p class="rt-status__next">{{ $this->nextStep() }}</p>
            </div>
            @if ($latestReturn)
                <blockquote class="rt-status__comment">
                    <p>{{ $latestReturn->comment }}</p>
                    <footer>{{ $latestReturn->user?->name ?? __('Reviewer') }}, {{ $latestReturn->created_at->format('j M Y, H:i') }}</footer>
                </blockquote>
            @endif
            <dl class="rt-meta">
                <div><dt>{{ __('Office') }}</dt><dd>{{ $record->department->name }}</dd></div>
                <div><dt>{{ __('Period') }}</dt><dd>{{ $record->periodLabel() }}</dd></div>
                <div><dt>{{ __('Prepared by') }}</dt><dd>{{ $record->preparedBy?->name ?? '—' }}</dd></div>
                <div>
                    <dt>{{ __('Submitted') }}</dt>
                    <dd>
                        @if ($record->submitted_at)
                            {{ $record->submittedBy?->name }}, {{ $record->submitted_at->format('j M Y, H:i') }}
                        @else
                            {{ __('Not yet') }}
                        @endif
                    </dd>
                </div>
            </dl>
        </section>

        @if ($results)
            <x-filament::section :heading="$results['heading']">
                @if (count($results['rows']) === 0)
                    <p class="rt-muted">{{ __('No results recorded.') }}</p>
                @else
                    <div class="rt-table-wrap">
                        <table class="rt-table rt-table--cards">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Target') }}</th>
                                    <th scope="col" class="rt-num">{{ $results['target_label'] }}</th>
                                    <th scope="col" class="rt-num">{{ $results['actual_label'] }}</th>
                                    <th scope="col" class="rt-num">{{ __('Achievement') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($results['rows'] as $row)
                                    <tr>
                                        <td>
                                            <span class="rt-strong">{{ $row['title'] }}</span>
                                            <span class="rt-muted rt-block">{{ $row['unit'] }}</span>
                                            @if (filled($row['note']))
                                                <span class="rt-note">{{ $row['note'] }}</span>
                                            @endif
                                        </td>
                                        <td class="rt-num" data-label="{{ $results['target_label'] }}">{{ $row['target'] }}</td>
                                        <td class="rt-num" data-label="{{ $results['actual_label'] }}">{{ $row['actual'] }}</td>
                                        <td class="rt-num" data-label="{{ __('Achievement') }}">{{ $row['achievement'] === null ? '—' : $row['achievement'].'%' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif

        <x-filament::section :heading="__('Report')">
            <dl class="rt-narrative">
                @foreach ($this->narrative() as $item)
                    <div>
                        <dt>{{ $item['label'] }}</dt>
                        <dd>{!! filled($item['value']) ? nl2br(e($item['value'])) : '<span class="rt-muted">'.e(__('Not provided')).'</span>' !!}</dd>
                    </div>
                @endforeach
                <div>
                    <dt>{{ __('Supporting evidence') }}</dt>
                    <dd>
                        @forelse ($evidence as $file)
                            @if ($file['url'])
                                <a href="{{ $file['url'] }}" class="rt-link rt-block" target="_blank" rel="noopener">{{ $file['name'] }}</a>
                            @else
                                <span class="rt-block rt-muted">{{ $file['name'] }} (file missing)</span>
                            @endif
                        @empty
                            <span class="rt-muted">{{ __('None attached') }}</span>
                        @endforelse
                    </dd>
                </div>
            </dl>
        </x-filament::section>

        <x-filament::section :heading="__('Review history')">
            @if ($reviews->isEmpty())
                <p class="rt-muted">{{ __('No submissions or decisions yet.') }}</p>
            @else
                <ol class="rt-timeline">
                    @foreach ($reviews as $review)
                        <li>
                            <x-filament::badge :color="$review->action->getColor()">{{ $review->action->getLabel() }}</x-filament::badge>
                            <span>{{ $review->user?->name ?? __('Unknown user') }}</span>
                            <time class="rt-muted" datetime="{{ $review->created_at->toIso8601String() }}">{{ $review->created_at->format('j M Y, H:i') }}</time>
                            @if (filled($review->comment))
                                <p class="rt-timeline__comment">{{ $review->comment }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
