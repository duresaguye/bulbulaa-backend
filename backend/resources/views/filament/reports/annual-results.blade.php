@php
    $format = fn (?float $value): string => $value === null ? '—' : number_format($value, floor($value) == $value ? 0 : 2);
@endphp

@if (count($results) === 0)
    <p class="rt-muted">No annual targets are assigned to this office for the selected year.</p>
@else
    <div class="rt-table-wrap">
        <table class="rt-table">
            <thead>
                <tr>
                    <th scope="col">Target</th>
                    <th scope="col" class="rt-num">Originally assigned</th>
                    <th scope="col" class="rt-num">Current target</th>
                    <th scope="col" class="rt-num">Approved result</th>
                    <th scope="col" class="rt-num">Achievement</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($results as $row)
                    <tr>
                        <td>
                            <span class="rt-strong">{{ $row['title'] }}</span>
                            <span class="rt-muted rt-block">{{ $row['unit'] }}, {{ \App\Enums\Aggregation::from($row['aggregation'])->getLabel() }}</span>
                        </td>
                        <td class="rt-num">{{ $format($row['original_target']) }}</td>
                        <td class="rt-num">
                            {{ $format($row['current_target']) }}
                            @if ($row['current_target'] != $row['original_target'])
                                <span class="rt-muted rt-block">Revised</span>
                            @endif
                        </td>
                        <td class="rt-num">{{ $format($row['actual']) }}</td>
                        <td class="rt-num">{{ $row['achievement'] === null ? '—' : $row['achievement'].'%' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
