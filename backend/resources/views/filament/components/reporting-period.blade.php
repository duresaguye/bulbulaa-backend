@php
    $today = \App\Services\EthiopianCalendar::today();
    $user = auth()->user();
    $office = $user instanceof \App\Models\User ? $user->primaryDepartment() : null;
@endphp

<div class="rt-period" aria-label="{{ __('Today in the Ethiopian calendar') }}">
    <span class="rt-period__date">
        <span class="rt-period__day">{{ $today['day'] }}</span>
        <span class="rt-period__month">
            {{ \App\Services\EthiopianCalendar::monthName($today['month']) }} {{ $today['year'] }}
            <span class="rt-period__week">{{ __('Week :week', ['week' => $today['week']]) }}</span>
        </span>
    </span>
    @if ($office)
        <span class="rt-period__office">{{ $office->name }}</span>
    @endif
</div>
