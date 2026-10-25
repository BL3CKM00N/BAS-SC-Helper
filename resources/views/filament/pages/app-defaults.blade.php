<x-filament-panels::page>
    <form wire:submit="save" style="display:flex;flex-direction:column;gap:1.5rem;align-items:flex-start">
        {{ $this->form }}

        <x-filament::button type="submit">Save</x-filament::button>
    </form>
</x-filament-panels::page>
