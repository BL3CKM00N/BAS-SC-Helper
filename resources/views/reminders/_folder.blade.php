<details class="group rounded-xl border border-white/10 bg-[#262623]" data-folder="{{ $folder->id }}" data-drop-folder="{{ $folder->id }}">
    <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3 [&::-webkit-details-marker]:hidden">
        <svg class="h-4 w-4 shrink-0 text-[#b8bab8] transition group-open:rotate-90" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" /></svg>
        <span class="min-w-0 flex-1 truncate font-medium">{{ $folder->name }}</span>
        @if ($folder->due_count)
            <span class="rounded-full bg-[#1da15e]/20 px-2 py-0.5 text-xs text-[#1da15e]">{{ $folder->due_count }} due now</span>
        @endif
        <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs text-[#b8bab8]">{{ $folder->items->count() + $folder->due_count }}</span>
    </summary>

    <div class="space-y-3 border-t border-white/10 p-4">
        @forelse ($folder->items as $reminder)
            @include('reminders._card', ['reminder' => $reminder, 'engine' => $engine, 'currentFolder' => $folder->id])
        @empty
            <p class="text-sm text-[#b8bab8]">{{ $folder->due_count ? 'Everything in here is due now, see the list at the top.' : 'Nothing in this folder yet. Pick it on a reminder to move it here.' }}</p>
        @endforelse

        <div class="flex flex-wrap items-center gap-2 pt-1">
            @if ($sorting->chosen['folders'] === 'manual')
                @foreach (['up' => 'Move up', 'down' => 'Move down'] as $direction => $label)
                    <form method="POST" action="{{ route('folders.reorder', $folder) }}">
                        @csrf
                        <input type="hidden" name="direction" value="{{ $direction }}">
                        <button type="submit" class="rounded-lg border border-white/15 px-3 py-1.5 text-xs transition hover:bg-white/5">{{ $label }}</button>
                    </form>
                @endforeach
            @endif
            <form method="POST" action="{{ route('folders.update', $folder) }}" class="flex items-center gap-2">
                @csrf
                @method('PATCH')
                <label for="folder-name-{{ $folder->id }}" class="sr-only">Folder name</label>
                <input id="folder-name-{{ $folder->id }}" name="name" type="text" required maxlength="60" value="{{ $folder->name }}"
                    class="w-44 rounded-lg border border-white/15 bg-[#1d1d1a] px-3 py-1.5 text-sm text-white outline-none focus:border-[#1da15e] focus:ring-1 focus:ring-[#1da15e]">
                <button type="submit" class="rounded-lg border border-white/15 px-3 py-1.5 text-xs transition hover:bg-white/5">Rename</button>
            </form>
            <form method="POST" action="{{ route('folders.destroy', $folder) }}"
                data-confirm="The folder is removed. Its reminders are kept and show up below again."
                data-confirm-title="Delete the folder &quot;{{ $folder->name }}&quot;?" data-confirm-button="Delete folder" data-confirm-tone="danger">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg border border-red-500/30 px-3 py-1.5 text-xs text-red-300 transition hover:bg-red-500/10">Delete folder</button>
            </form>
        </div>
    </div>
</details>
