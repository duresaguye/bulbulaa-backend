<x-filament-panels::page>
    <x-filament::tabs label="Report type">
        @foreach ($this->tabs() as $key => $tab)
            <x-filament::tabs.item
                :active="$this->type === $key"
                wire:click="$set('type', '{{ $key }}')"
                :badge="$tab['count'] ?: null"
            >
                {{ $tab['label'] }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
