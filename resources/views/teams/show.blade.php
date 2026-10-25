@extends('layouts.shell')

@section('title', $team->name.' - '.config('app.name', 'BAS SC Helper'))

@section('page')
    <a href="{{ route('teams.index') }}" class="text-sm text-[#b8bab8] hover:text-white">&larr; Teams</a>
    <h1 class="mt-2 text-2xl font-semibold">{{ $team->name }}</h1>

    <section class="mt-8 rounded-2xl border border-white/10 bg-[#262623]">
        <h2 class="border-b border-white/10 px-6 py-4 text-sm font-medium text-[#b8bab8]">Members</h2>
        <ul class="divide-y divide-white/10">
            @foreach ($members as $member)
                <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-3">
                    <div>
                        <p class="text-sm font-medium">{{ $member->name }}</p>
                        <p class="text-xs text-[#b8bab8]">{{ $member->email }}</p>
                    </div>

                    @if ($canManage)
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('teams.members.update', [$team, $member]) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <select name="role" aria-label="Role of {{ $member->name }}" onchange="this.form.submit()"
                                    class="rounded-lg border border-white/15 bg-[#1d1d1a] px-2 py-1.5 text-sm">
                                    <option value="member" @selected($member->pivot->role === 'member')>Member</option>
                                    <option value="manager" @selected($member->pivot->role === 'manager')>Manager</option>
                                </select>
                            </form>
                            <form method="POST" action="{{ route('teams.members.remove', [$team, $member]) }}" onsubmit="return confirm('Remove {{ $member->name }} from the team?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-white/15 px-3 py-1.5 text-sm text-red-300 transition hover:bg-red-500/10">Remove</button>
                            </form>
                        </div>
                    @else
                        <span class="rounded-full bg-white/10 px-2.5 py-0.5 text-xs {{ $member->pivot->role === 'manager' ? 'text-[#7be0a8]' : 'text-[#b8bab8]' }}">{{ $member->pivot->role }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    @if ($canManage)
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-white/10 bg-[#262623] p-6">
                <h2 class="text-sm font-medium text-[#b8bab8]">Add a member</h2>
                <form method="POST" action="{{ route('teams.members.add', $team) }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label for="email" class="mb-1 block text-sm text-[#b8bab8]">Email of an existing user</label>
                        <input id="email" name="email" type="email" required value="{{ old('email') }}"
                            class="w-full rounded-lg border border-white/15 bg-[#1d1d1a] px-3 py-2.5 text-sm outline-none focus:border-[#1da15e]">
                    </div>
                    <div>
                        <label for="role" class="mb-1 block text-sm text-[#b8bab8]">Role</label>
                        <select id="role" name="role" class="w-full rounded-lg border border-white/15 bg-[#1d1d1a] px-3 py-2.5 text-sm">
                            <option value="member">Member</option>
                            <option value="manager">Manager</option>
                        </select>
                    </div>
                    <button type="submit" class="rounded-lg bg-[#1da15e] px-4 py-2.5 text-sm font-medium transition hover:bg-[#178a4f]">Add to team</button>
                </form>
            </section>

            <section class="rounded-2xl border border-white/10 bg-[#262623] p-6">
                <h2 class="text-sm font-medium text-[#b8bab8]">Team settings</h2>
                <form method="POST" action="{{ route('teams.update', $team) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PATCH')
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" name="members_can_create" value="1" @checked($team->members_can_create) class="mt-0.5 size-4 accent-[#1da15e]">
                        <span>Members can create team reminders<span class="block text-xs text-[#b8bab8]">Managers can always create them.</span></span>
                    </label>
                    <button type="submit" class="rounded-lg border border-white/15 px-4 py-2.5 text-sm transition hover:bg-white/5">Save</button>
                </form>
            </section>
        </div>
    @endif
@endsection
