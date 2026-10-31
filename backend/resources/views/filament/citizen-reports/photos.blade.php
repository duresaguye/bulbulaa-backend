@php
    $record = $getRecord();
@endphp

<div class="rt-photo-grid">
    @foreach ($record->photos ?? [] as $index => $path)
        @php($url = route('filament.admin.citizen-reports.photo', ['report' => $record, 'index' => $index]))
        <a href="{{ $url }}" target="_blank" rel="noopener" class="rt-photo">
            <img src="{{ $url }}" alt="{{ __('Photo :number of :total', ['number' => $index + 1, 'total' => count($record->photos)]) }}" loading="lazy">
        </a>
    @endforeach
</div>
