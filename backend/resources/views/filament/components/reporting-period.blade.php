@php
    $today = \App\Services\EthiopianCalendar::today();
    $user = auth()->user();
    $office = $user instanceof \App\Models\User ? $user->primaryDepartment() : null;
@endphp

<div class="rt-period" aria-label="Current reporting period">
    <span class="rt-period__date">
        {{ \App\Services\EthiopianCalendar::monthName($today['month']) }} {{ $today['day'] }}, {{ $today['year'] }}
        <span class="rt-muted">week {{ $today['week'] }}</span>
    </span>
    @if ($office)
        <span class="rt-period__office">{{ $office->name }}</span>
    @endif
</div>
