@extends('layouts.shell')

@section('title', trim($__env->yieldContent('account-title')).' - Account - '.config('app.name', 'BAS SC Helper'))

@section('page')
    @php
        $accountTabs = [
            ['Profile', route('account.profile'), request()->routeIs('account.profile')],
            ['Notifications', route('account.notifications'), request()->routeIs('account.notifications')],
            ['Work hours', route('account.work-hours'), request()->routeIs('account.work-hours*')],
            ['Security', route('account.security'), request()->routeIs('account.security*')],
        ];
    @endphp

    <h1 class="text-2xl font-semibold">Account</h1>

    <nav class="mt-4 flex gap-0 overflow-x-auto sm:gap-1 border-b border-white/10 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="Account">
        @foreach ($accountTabs as [$label, $url, $active])
            <a href="{{ $url }}" @if ($active) aria-current="page" @endif
                class="-mb-px whitespace-nowrap border-b-2 px-2.5 py-2.5 text-[13px] font-medium transition sm:px-3 sm:text-sm {{ $active ? 'border-[#1da15e] text-white' : 'border-transparent text-[#b8bab8] hover:text-white' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="mt-6 space-y-6">
        @yield('account')
    </div>
@endsection
