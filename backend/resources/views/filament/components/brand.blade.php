@php($town = config('app.town_name'))
<div class="rt-brand" aria-label="{{ __(':town administration', ['town' => $town]) }}">
    <svg class="rt-brand__mark" viewBox="0 0 32 32" aria-hidden="true">
        <rect x="1" y="1" width="30" height="30" rx="7" fill="currentColor" />
        {{-- A town hall on rising ground: the administration, and the land it serves. --}}
        <path d="M6 24.5c3.2-1.6 6.5-2.4 10-2.4s6.8.8 10 2.4" fill="none" stroke="var(--rt-gold)" stroke-width="1.8" stroke-linecap="round" />
        <path d="M10 20.5v-6l6-4 6 4v6M14 20.5v-3.5h4v3.5" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <span class="rt-brand__copy">
        <span class="rt-brand__name">{{ $town }}</span>
        <span class="rt-brand__tagline">{{ __('Planning and reporting') }}</span>
    </span>
</div>
