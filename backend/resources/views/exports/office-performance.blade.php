@extends('exports.layout')

@section('content')
    @if (count($document['rows']) === 0)
        <p class="empty">{{ __('No offices match this filter.') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th scope="col">{{ __('Office') }}</th>
                    <th scope="col">{{ __('Targets') }}</th>
                    <th scope="col">{{ $document['week_label'] }}</th>
                    <th scope="col">{{ $document['month_label'] }}</th>
                    <th scope="col" class="num">{{ __('Overdue') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($document['rows'] as $row)
                    <tr>
                        <td style="padding-inline-start: {{ 6 + $row['depth'] * 14 }}px"><strong>{{ $row['office'] }}</strong></td>
                        <td>
                            @if (! $row['has_targets'])
                                <span class="empty">{{ __('None assigned') }}</span>
                            @else
                                {{ collect([
                                    $row['on_track'] ? __(':count on track', ['count' => $row['on_track']]) : null,
                                    $row['at_risk'] ? __(':count at risk', ['count' => $row['at_risk']]) : null,
                                    $row['behind'] ? __(':count behind', ['count' => $row['behind']]) : null,
                                    $row['not_started'] ? __(':count not due', ['count' => $row['not_started']]) : null,
                                ])->filter()->join(', ') }}
                            @endif
                        </td>
                        <td>{{ $row['week'] }}</td>
                        <td>{{ $row['month'] }}</td>
                        <td class="num">{{ $row['overdue'] }}</td>
                    </tr>
                    @foreach ($row['targets'] as $target)
                        <tr class="nested">
                            <td style="padding-inline-start: {{ 20 + $row['depth'] * 14 }}px">{{ $target['title'] }} <span class="unit">{{ $target['unit'] }}</span></td>
                            <td>{{ __(':actual of :target', ['actual' => $target['actual'], 'target' => $target['target']]) }}</td>
                            <td>{{ __('Expected by now') }}: {{ $target['expected'] }}</td>
                            <td>{{ $target['achievement'] === null ? '—' : $target['achievement'].'%' }}</td>
                            <td class="num">{{ $target['pace'] }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
        <p class="unit">{{ __('"Overdue" counts weekly and monthly reports this year that are past their deadline and were not submitted. Offices only owe reports for months in which they have monthly targets.') }}</p>
    @endif
@endsection
