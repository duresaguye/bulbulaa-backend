<x-filament-panels::page>
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
            @if (count($this->unitOptions()) > 0)
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
            <label class="rt-select">
                <span>Show</span>
                <select wire:model.live="status">
                    @foreach ($this->statusOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <span wire:loading class="rt-muted rt-small">Updating…</span>
        </div>

        @php($rows = $this->rows())
        <x-filament::section>
            @if ($rows->isEmpty())
                <p class="rt-muted">No offices match this filter.</p>
            @else
                @include('filament.partials.office-table', ['rows' => $rows, 'expandable' => true, 'expanded' => $this->expanded, 'compact' => $this->status !== 'all'])
            @endif
        </x-filament::section>

        <p class="rt-muted rt-small">
            "Overdue" counts weekly and monthly reports this year that are past their deadline and were not submitted.
            Offices only owe reports for months in which they have monthly targets.
        </p>
    </div>
</x-filament-panels::page>
