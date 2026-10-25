<x-filament-panels::page>
    @php($totals = $this->totals())
    @php($failure = $this->lastFailure())

    <div class="grid gap-4 sm:grid-cols-3">
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">People who get notifications</p>
            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $totals['reachable'] }} of {{ $totals['people'] }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Devices</p>
            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $totals['devices'] }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Failed pushes</p>
            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $failure['count'] ?? 0 }}</p>
            @if ($failure)
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Last {{ $failure['at']->diffForHumans() }}: {{ \Illuminate\Support\Str::limit($failure['reason'], 90) }}</p>
            @endif
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
