@extends('layouts.shell')

@section('title', $team->name.' - '.config('app.name', 'BAS SC Helper'))

@section('page')
    <a href="{{ route('teams.index') }}" class="text-sm text-[#b8bab8] hover:text-white">&larr; Teams</a>
    <h1 class="mt-2 text-2xl font-semibold">{{ $team->name }}</h1>
    <p class="mt-1 text-sm text-[#b8bab8]">
        @if ($team->isOpen())
            This team has no manager at the moment. An admin can assign one.
        @else
            Manager: {{ $team->manager->name }}
        @endif
    </p>

    <section class="mt-8 rounded-2xl border border-white/10 bg-[#262623]">
        <h2 class="border-b border-white/10 px-6 py-4 text-sm font-medium text-[#b8bab8]">Members</h2>
        <ul class="divide-y divide-white/10">
            @foreach ($members as $member)
                <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-3">
                    <div>
                        <p class="text-sm font-medium">{{ $member->name }}</p>
                        <p class="text-xs text-[#b8bab8]">{{ $member->email }}</p>
                    </div>
                    @if ($team->isManagedBy($member))
                        <span class="rounded-full bg-[#1da15e]/15 px-2.5 py-0.5 text-xs text-[#7be0a8]">manager</span>
                    @elseif ($canManage)
                        <form method="POST" action="{{ route('teams.requests.store', $team) }}" onsubmit="return confirm('Ask the admins to remove {{ addslashes($member->name) }} from the team?')">
                            @csrf
                            <input type="hidden" name="type" value="remove_member">
                            <input type="hidden" name="user_id" value="{{ $member->id }}">
                            <button type="submit" class="rounded-lg border border-white/15 px-3 py-1.5 text-xs text-[#b8bab8] transition hover:bg-white/5">Request removal</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    @if ($canManage)
        @php
            $inputClass = 'w-full rounded-lg border border-white/15 bg-[#1d1d1a] px-3 py-2.5 text-sm text-white outline-none focus:border-[#1da15e]';
        @endphp

        <section class="mt-6 rounded-2xl border border-white/10 bg-[#262623] p-6">
            <h2 class="text-sm font-medium text-[#b8bab8]">Change who is in the team</h2>
            <p class="mt-1 max-w-2xl text-sm text-[#b8bab8]">You cannot change the members yourself. Send a request and an admin will approve it.</p>

            <details class="mt-4 max-w-2xl rounded-xl border border-white/10 bg-[#1d1d1a] p-4" @open(old('type') === 'new_member')>
                <summary class="cursor-pointer text-sm font-medium">Request a new account</summary>
                <form method="POST" action="{{ route('teams.requests.store', $team) }}" class="mt-4 space-y-3">
                    @csrf
                    <input type="hidden" name="type" value="new_member">
                    <div>
                        <label for="new-name" class="mb-1 block text-sm text-[#b8bab8]">Name</label>
                        <input id="new-name" name="name" type="text" required maxlength="255" value="{{ old('type') === 'new_member' ? old('name') : '' }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label for="new-email" class="mb-1 block text-sm text-[#b8bab8]">Email address</label>
                        <input id="new-email" name="email" type="email" required maxlength="255" value="{{ old('type') === 'new_member' ? old('email') : '' }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label for="new-note" class="mb-1 block text-sm text-[#b8bab8]">Note for the admins (optional)</label>
                        <input id="new-note" name="note" type="text" maxlength="500" class="{{ $inputClass }}">
                    </div>
                    <button type="submit" class="rounded-lg bg-[#1da15e] px-4 py-2.5 text-sm font-medium transition hover:bg-[#178a4f]">Send request</button>
                </form>
            </details>

            <details class="mt-3 max-w-2xl rounded-xl border border-white/10 bg-[#1d1d1a] p-4" @open(old('type') === 'add_member')>
                <summary class="cursor-pointer text-sm font-medium">Request to add someone who already has an account</summary>
                <form method="POST" action="{{ route('teams.requests.store', $team) }}" class="mt-4 space-y-3">
                    @csrf
                    <input type="hidden" name="type" value="add_member">
                    <div>
                        <label for="add-email" class="mb-1 block text-sm text-[#b8bab8]">Their email address</label>
                        <input id="add-email" name="email" type="email" required maxlength="255" value="{{ old('type') === 'add_member' ? old('email') : '' }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label for="add-note" class="mb-1 block text-sm text-[#b8bab8]">Note for the admins (optional)</label>
                        <input id="add-note" name="note" type="text" maxlength="500" class="{{ $inputClass }}">
                    </div>
                    <button type="submit" class="rounded-lg bg-[#1da15e] px-4 py-2.5 text-sm font-medium transition hover:bg-[#178a4f]">Send request</button>
                </form>
            </details>
        </section>

        <section class="mt-6 rounded-2xl border border-white/10 bg-[#262623]" aria-labelledby="requests-heading">
            <h2 id="requests-heading" class="border-b border-white/10 px-6 py-4 text-sm font-medium text-[#b8bab8]">Your requests</h2>
            @if ($requests->isEmpty())
                <p class="px-6 py-4 text-sm text-[#b8bab8]">No requests yet.</p>
            @else
                <ul class="divide-y divide-white/10">
                    @foreach ($requests as $item)
                        <li class="flex flex-wrap items-start justify-between gap-3 px-6 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium">{{ $item->summary() }}</p>
                                <p class="mt-0.5 text-xs text-[#b8bab8]">
                                    Sent {{ auth()->user()->local($item->created_at)->format('D j M H:i') }}
                                    @if ($item->decided_at) &middot; decided {{ auth()->user()->local($item->decided_at)->format('D j M H:i') }} @endif
                                </p>
                                @if ($item->decision_note)
                                    <p class="mt-1 text-xs text-[#b8bab8]">Reason: {{ $item->decision_note }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full px-2.5 py-0.5 text-xs {{ ['pending' => 'bg-amber-500/15 text-amber-300', 'approved' => 'bg-[#1da15e]/15 text-[#7be0a8]', 'rejected' => 'bg-red-500/15 text-red-300'][$item->status] }}">{{ $item->status }}</span>
                                @if ($item->isPending() && $item->requested_by === auth()->id())
                                    <form method="POST" action="{{ route('teams.requests.destroy', [$team, $item]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-white/15 px-3 py-1.5 text-xs transition hover:bg-white/5">Withdraw</button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="mt-6 rounded-2xl border border-white/10 bg-[#262623] p-6">
            <h2 class="text-sm font-medium text-[#b8bab8]">Team settings</h2>
            <form method="POST" action="{{ route('teams.update', $team) }}" class="mt-4 max-w-2xl space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label for="name" class="mb-1 block text-sm text-[#b8bab8]">Team name</label>
                    <input id="name" name="name" type="text" required maxlength="100" value="{{ old('name', $team->name) }}"
                        class="w-full rounded-lg border border-white/15 bg-[#1d1d1a] px-3 py-2.5 text-sm outline-none focus:border-[#1da15e]">
                </div>
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" name="members_can_create" value="1" @checked($team->members_can_create) class="mt-0.5 size-4 accent-[#1da15e]">
                    <span>Members can create team reminders<span class="block text-xs text-[#b8bab8]">You can always create them.</span></span>
                </label>
                <button type="submit" class="rounded-lg bg-[#1da15e] px-4 py-2.5 text-sm font-medium transition hover:bg-[#178a4f]">Save</button>
            </form>
        </section>
    @endif
@endsection
