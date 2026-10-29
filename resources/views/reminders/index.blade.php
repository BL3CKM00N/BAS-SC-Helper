@extends('layouts.shell')

@section('title', 'Reminders - '.config('app.name', 'BAS SC Helper'))

@section('page')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Reminders</h1>
        <div class="flex items-center gap-2">
            <details class="relative" data-popover @if ($errors->has('name')) open @endif>
                <summary class="cursor-pointer list-none rounded-lg border border-white/15 px-4 py-2 text-sm font-medium transition hover:bg-white/5 [&::-webkit-details-marker]:hidden">New folder</summary>
                <form method="POST" action="{{ route('folders.store') }}" class="absolute right-0 z-20 mt-2 w-64 rounded-xl border border-white/10 bg-[#262623] p-3 shadow-2xl">
                    @csrf
                    <label for="new-folder" class="mb-1 block text-xs text-[#b8bab8]">Folder name</label>
                    <div class="flex items-center gap-2">
                        <input id="new-folder" name="name" type="text" required maxlength="60" value="{{ old('name') }}"
                            class="min-w-0 flex-1 rounded-lg border border-white/15 bg-[#1d1d1a] px-3 py-1.5 text-sm text-white outline-none focus:border-[#1da15e] focus:ring-1 focus:ring-[#1da15e]">
                        <button type="submit" class="rounded-lg bg-[#1da15e] px-3 py-1.5 text-sm font-medium transition hover:bg-[#178a4f]">Add</button>
                    </div>
                    @error('name')
                        <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </form>
            </details>
            <a href="{{ route('reminders.create') }}" class="rounded-lg bg-[#1da15e] px-4 py-2 text-sm font-medium transition hover:bg-[#178a4f]">New reminder</a>
        </div>
    </div>

    <nav class="mt-4 flex gap-1 overflow-x-auto" aria-label="Filter reminders">
        @php
            $filters = [['all', 'All'], ['mine', 'Mine']];
            foreach ($teams as $team) {
                $filters[] = ['team:'.$team->id, $team->name];
            }
        @endphp
        @foreach ($filters as [$value, $label])
            <a href="{{ route('reminders.index', ['scope' => $value]) }}" @if ($scope === $value) aria-current="true" @endif
                class="whitespace-nowrap rounded-full px-3 py-1 text-sm {{ $scope === $value ? 'bg-white/10' : 'text-[#b8bab8] hover:bg-white/5' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <section class="mt-8" aria-labelledby="due-heading">
        <h2 id="due-heading" class="mb-3 text-sm font-medium text-[#b8bab8]">Due now</h2>
        <div class="space-y-3" data-due-now data-reload-after-action>
            @include('reminders._due', ['due' => $due, 'engine' => $engine])
        </div>
    </section>

    <section class="mt-10" aria-labelledby="folders-heading">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 id="folders-heading" class="text-sm font-medium text-[#b8bab8]">Folders</h2>
        </div>

        @if ($folders->isEmpty())
            <p class="rounded-xl border border-dashed border-white/15 px-4 py-6 text-center text-sm text-[#b8bab8]">Use New folder at the top to group reminders and keep this page tidy. Folders are closed until you open them, and you can drag a reminder onto one.</p>
        @else
            <div class="space-y-3" data-folders>
                @foreach ($folders as $folder)
                    @include('reminders._folder', ['folder' => $folder, 'engine' => $engine])
                @endforeach
            </div>
        @endif
    </section>

    <section class="mt-10" aria-labelledby="upcoming-heading" data-drop-folder="">
        <h2 id="upcoming-heading" class="mb-3 text-sm font-medium text-[#b8bab8]">Upcoming</h2>
        <div class="space-y-3">
            @forelse ($upcoming as $reminder)
                @include('reminders._card', ['reminder' => $reminder, 'engine' => $engine])
            @empty
                <p class="rounded-xl border border-dashed border-white/15 px-4 py-6 text-center text-sm text-[#b8bab8]">No upcoming reminders.</p>
            @endforelse
        </div>
    </section>

    @if ($done->isNotEmpty())
        <section class="mt-10" aria-labelledby="done-heading" data-drop-folder="">
            <h2 id="done-heading" class="mb-3 text-sm font-medium text-[#b8bab8]">Done</h2>
            <div class="space-y-3">
                @foreach ($done as $reminder)
                    @include('reminders._card', ['reminder' => $reminder, 'engine' => $engine])
                @endforeach
            </div>
        </section>
    @endif
@endsection
