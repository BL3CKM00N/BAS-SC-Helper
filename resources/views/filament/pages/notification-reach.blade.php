<x-filament-panels::page>
    @php($totals = $this->totals())
    @php($failure = $this->lastFailure())

    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem">
        <x-filament::section>
            <p style="font-size:.875rem;color:var(--gray-400)">People who get notifications</p>
            <p style="margin-top:.25rem;font-size:1.5rem;font-weight:600;color:#fff">{{ $totals['reachable'] }} of {{ $totals['people'] }}</p>
        </x-filament::section>
        <x-filament::section>
            <p style="font-size:.875rem;color:var(--gray-400)">Devices</p>
            <p style="margin-top:.25rem;font-size:1.5rem;font-weight:600;color:#fff">{{ $totals['devices'] }}</p>
        </x-filament::section>
        <x-filament::section>
            <p style="font-size:.875rem;color:var(--gray-400)">Failed pushes</p>
            <p style="margin-top:.25rem;font-size:1.5rem;font-weight:600;color:#fff">{{ $failure['count'] ?? 0 }}</p>
            @if ($failure)
                <p style="margin-top:.25rem;font-size:.75rem;color:var(--gray-400)">Last {{ $failure['at']->diffForHumans() }}: {{ \Illuminate\Support\Str::limit($failure['reason'], 90) }}</p>
            @endif
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
