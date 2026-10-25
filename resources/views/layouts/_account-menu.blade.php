@php
    $user = auth()->user();
    $roleLabel = ['admin' => 'Admin', 'team_manager' => 'Team manager', 'user' => 'User'][$user->role?->name] ?? 'User';
    $dropdown = $variant === 'dropdown';

    $links = [
        ['Profile', route('account.profile'), request()->routeIs('account.profile')],
        ['Notifications', route('account.notifications'), request()->routeIs('account.notifications')],
        ['Work hours', route('account.work-hours'), request()->routeIs('account.work-hours*')],
        ['Security', route('account.security'), request()->routeIs('account.security*')],
    ];

    $linkClass = $dropdown ? 'rounded-lg px-3 py-2 text-sm' : 'rounded-lg px-3 py-2.5 text-base';
@endphp

<div class="{{ $dropdown ? 'px-3 pb-3 pt-2' : 'px-3 pb-3' }}">
    <p class="truncate text-sm font-medium">{{ $user->name }}</p>
    <p class="truncate text-xs text-[#b8bab8]">{{ $user->email }}</p>
    <p class="mt-1 text-xs text-[#7be0a8]">{{ $roleLabel }}</p>
</div>

<div class="flex flex-col gap-0.5 {{ $dropdown ? 'border-t border-white/10 pt-2' : '' }}">
    @foreach ($links as [$label, $url, $active])
        <a href="{{ $url }}" @if ($active) aria-current="page" @endif
            class="flex items-center justify-between transition {{ $linkClass }} {{ $active ? 'bg-white/10' : 'text-[#b8bab8] hover:bg-white/5 hover:text-white' }}">
            {{ $label }}
            @if ($label === 'Notifications')
                <span data-notify-badge hidden class="rounded-full bg-amber-400/15 px-2 py-0.5 text-xs text-amber-300">Off here</span>
            @endif
        </a>
    @endforeach

    @if ($user->isAdmin())
        <a href="/admin" class="transition text-[#b8bab8] hover:bg-white/5 hover:text-white {{ $linkClass }}">Admin panel</a>
    @endif
</div>

<form method="POST" action="{{ route('logout') }}" class="{{ $dropdown ? 'mt-2 border-t border-white/10 pt-2' : 'mt-3' }}">
    @csrf
    <button type="submit" class="w-full text-left transition text-[#b8bab8] hover:bg-white/5 hover:text-white {{ $linkClass }}">Sign out</button>
</form>
