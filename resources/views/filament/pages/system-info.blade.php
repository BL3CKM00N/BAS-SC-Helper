<x-filament-panels::page>
    <x-filament::section>
        <dl class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($this->facts() as $label => $value)
                <div class="flex justify-between gap-4 py-2 text-sm">
                    <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>
</x-filament-panels::page>
