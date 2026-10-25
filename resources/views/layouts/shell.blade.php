@extends('layouts.app')

@section('body-class', 'flex flex-col')

@section('content')
    <header class="border-b border-white/10 bg-[#262623]">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-3 px-4 py-3">
            <a href="{{ route('dashboard') }}" class="flex items-center">
                <img src="{{ $logoUrl }}" alt="BAS World" class="h-7 w-auto">
            </a>

            @php
                $tabs = [
                    ['Dashboard', route('dashboard'), request()->routeIs('dashboard')],
                    ['Teams', route('teams.index'), request()->routeIs('teams.*')],
                ];
                if (auth()->user()->isAdmin()) {
                    $tabs[] = ['Admin', '/admin', false];
                }
            @endphp

            <nav class="order-last flex w-full gap-1 overflow-x-auto sm:order-none sm:w-auto" aria-label="Main">
                @foreach ($tabs as [$label, $url, $active])
                    <a href="{{ $url }}" @if ($active) aria-current="page" @endif
                        class="whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $active ? 'bg-white/10' : 'text-[#b8bab8] hover:bg-white/5 hover:text-white' }}">{{ $label }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-3">
                <span class="hidden text-sm text-[#b8bab8] sm:inline">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-white/15 px-3 py-1.5 text-sm transition hover:bg-white/5">Sign out</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 pb-16">
        @if (session('status'))
            <p role="status" class="mb-6 rounded-lg border border-[#1da15e]/40 bg-[#1da15e]/10 px-4 py-2.5 text-sm text-[#7be0a8]">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <div role="alert" class="mb-6 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2.5 text-sm text-red-300">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @yield('page')
    </main>
@endsection
