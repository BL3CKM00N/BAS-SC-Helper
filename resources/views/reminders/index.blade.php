@extends('layouts.shell')

@section('title', 'Reminders - '.config('app.name', 'BAS SC Helper'))

@section('page')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Reminders</h1>
        <a href="{{ route('reminders.create') }}" class="rounded-lg bg-[#1da15e] px-4 py-2 text-sm font-medium transition hover:bg-[#178a4f]">New reminder</a>
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

    <section class="mt-10" aria-labelledby="upcoming-heading">
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
        <section class="mt-10" aria-labelledby="done-heading">
            <h2 id="done-heading" class="mb-3 text-sm font-medium text-[#b8bab8]">Done</h2>
            <div class="space-y-3">
                @foreach ($done as $reminder)
                    @include('reminders._card', ['reminder' => $reminder, 'engine' => $engine])
                @endforeach
            </div>
        </section>
    @endif
@endsection
