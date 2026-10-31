@extends('exports.layout')

@section('content')
    <dl class="meta">
        @foreach ($document['meta'] as $item)
            <div><dt>{{ $item['label'] }}</dt><dd>{{ $item['value'] }}</dd></div>
        @endforeach
    </dl>

    @if ($document['results'])
        @php($results = $document['results'])
        <h2>{{ $results['heading'] }}</h2>
        @if (count($results['rows']) === 0)
            <p class="empty">{{ __('No results recorded.') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th scope="col">{{ __('Target') }}</th>
                        <th scope="col" class="num">{{ $results['target_label'] }}</th>
                        <th scope="col" class="num">{{ $results['actual_label'] }}</th>
                        <th scope="col" class="num">{{ __('Achievement') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($results['rows'] as $row)
                        <tr>
                            <td>
                                {{ $row['title'] }}
                                <span class="unit">{{ $row['unit'] }}</span>
                                @if (filled($row['note']))
                                    <span class="note">{{ $row['note'] }}</span>
                                @endif
                            </td>
                            <td class="num">{{ $row['target'] }}</td>
                            <td class="num">{{ $row['actual'] }}</td>
                            <td class="num">{{ $row['achievement'] === null ? '—' : $row['achievement'].'%' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    <h2>{{ __('Report') }}</h2>
    <dl class="narrative">
        @foreach ($document['narrative'] as $item)
            <dt>{{ $item['label'] }}</dt>
            <dd class="{{ filled($item['value']) ? '' : 'empty' }}">{{ filled($item['value']) ? $item['value'] : __('Not provided') }}</dd>
        @endforeach
    </dl>

    <h2>{{ __('Review history') }}</h2>
    @if (count($document['reviews']) === 0)
        <p class="empty">{{ __('No submissions or decisions yet.') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th scope="col">{{ __('Status') }}</th>
                    <th scope="col">{{ __('By') }}</th>
                    <th scope="col">{{ __('When') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($document['reviews'] as $review)
                    <tr>
                        <td>
                            {{ $review['action'] }}
                            @if (filled($review['comment']))
                                <span class="note">{{ $review['comment'] }}</span>
                            @endif
                        </td>
                        <td>{{ $review['person'] }}</td>
                        <td>{{ $review['when'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="signatures">
        <div>{{ __('Prepared by') }}</div>
        <div>{{ __('Reviewer') }}</div>
    </div>
@endsection
