<x-filament-panels::page>
    {{-- Секция с фильтрами --}}
    <x-filament-panels::form wire:submit="submit">
        {{ $this->form }}
    </x-filament-panels::form>

    {{-- Секция с виджетами --}}
    <x-filament-widgets::widgets
        :widgets="$this->getHeaderWidgets()"
        :columns="$this->getHeaderWidgetsColumns()"
    />
</x-filament-panels::page>