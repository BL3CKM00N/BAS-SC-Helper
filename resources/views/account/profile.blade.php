@extends('account.layout')

@section('account-title', 'Profile')

@section('account')
    @php
        $roleLabel = ['admin' => 'Admin', 'team_manager' => 'Team manager', 'user' => 'User'][$user->role?->name] ?? 'User';
    @endphp

    <section class="rounded-2xl border border-white/10 bg-[#262623] p-6">
        <h2 class="text-sm font-medium text-[#b8bab8]">Your details</h2>

        <dl class="mt-5 grid max-w-2xl gap-x-8 gap-y-5 sm:grid-cols-2">
            <div>
                <dt class="text-xs text-[#b8bab8]">Name</dt>
                <dd class="mt-1 text-sm">{{ $user->name }}</dd>
            </div>
            <div>
                <dt class="text-xs text-[#b8bab8]">Email address</dt>
                <dd class="mt-1 break-all text-sm">{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="text-xs text-[#b8bab8]">Role</dt>
                <dd class="mt-1 text-sm">{{ $roleLabel }}</dd>
            </div>
            <div>
                <dt class="text-xs text-[#b8bab8]">Account created</dt>
                <dd class="mt-1 text-sm">{{ $user->local($user->created_at)->format('j F Y') }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs text-[#b8bab8]">{{ str('Team')->plural($teams->count()) }}</dt>
                <dd class="mt-2 flex flex-wrap gap-2 text-sm">
                    @forelse ($teams as $team)
                        <a href="{{ route('teams.show', $team) }}" class="rounded-full bg-white/10 px-3 py-1 transition hover:bg-white/15">
                            {{ $team->name }}@if ($team->isManagedBy($user)) <span class="text-[#7be0a8]">(you manage it)</span>@endif
                        </a>
                    @empty
                        <span class="text-[#b8bab8]">You are not in a team.</span>
                    @endforelse
                </dd>
            </div>
        </dl>

        <p class="mt-6 max-w-2xl text-sm text-[#b8bab8]">Your name, email address and role are managed by an admin. Ask them if something is wrong.</p>
    </section>
@endsection
