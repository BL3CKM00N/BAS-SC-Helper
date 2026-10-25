<x-filament-panels::page>
    <x-filament::section>
        <div style="display:grid;grid-template-columns:minmax(9rem,14rem) 1fr;row-gap:.75rem;column-gap:1.5rem;font-size:.875rem">
            @foreach ($this->facts() as $label => $value)
                <div style="color:var(--gray-400)">{{ $label }}</div>
                <div style="color:#fff;font-weight:500;word-break:break-word">{{ $value }}</div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
