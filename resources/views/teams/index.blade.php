@extends('layouts.shell')

@section('title', 'Teams - '.config('app.name', 'BAS SC Helper'))

@section('page')
    <h1 class="text-2xl font-semibold">Teams</h1>
    <p class="mt-1 text-sm text-[#b8bab8]">The teams you are in. Admins create teams and assign managers in the admin panel.</p>

    <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($teams as $team)
            <li>
                <a href="{{ route('teams.show', $team) }}" class="block rounded-2xl border border-white/10 bg-[#262623] p-6 transition hover:border-white/25">
                    <h2 class="text-lg font-medium">{{ $team->name }}</h2>
                    <p class="mt-1 text-sm text-[#b8bab8]">{{ $team->users_count }} {{ str('member')->plural($team->users_count) }}</p>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        @if ($team->isManagedBy(auth()->user()))
                            <span class="rounded-full bg-[#1da15e]/15 px-2.5 py-0.5 text-[#7be0a8]">You manage this team</span>
                        @elseif ($team->isOpen())
                            <span class="rounded-full bg-amber-500/15 px-2.5 py-0.5 text-amber-300">No manager yet</span>
                        @else
                            <span class="rounded-full bg-white/10 px-2.5 py-0.5 text-[#b8bab8]">Manager: {{ $team->manager->name }}</span>
                        @endif
                    </div>
                </a>
            </li>
        @endforeach
    </ul>
@endsection
