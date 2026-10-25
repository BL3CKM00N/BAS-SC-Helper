@extends('layouts.shell')

@section('title', 'Teams - '.config('app.name', 'BAS SC Helper'))

@section('page')
    <h1 class="text-2xl font-semibold">Teams</h1>
    <p class="mt-1 text-sm text-[#b8bab8]">Teams you are a member of. Admins create teams in the admin panel.</p>

    @if ($teams->isEmpty())
        <p class="mt-8 rounded-2xl border border-dashed border-white/15 bg-[#262623] p-6 text-sm text-[#b8bab8]">You are not in a team yet.</p>
    @else
        <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($teams as $team)
                <li>
                    <a href="{{ route('teams.show', $team) }}" class="block rounded-2xl border border-white/10 bg-[#262623] p-6 transition hover:border-white/25">
                        <h2 class="text-lg font-medium">{{ $team->name }}</h2>
                        <p class="mt-1 text-sm text-[#b8bab8]">{{ $team->users_count }} {{ str('member')->plural($team->users_count) }}</p>
                        <span class="mt-4 inline-block rounded-full bg-white/10 px-2.5 py-0.5 text-xs {{ $team->pivot->role === 'manager' ? 'text-[#7be0a8]' : 'text-[#b8bab8]' }}">{{ $team->pivot->role }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
