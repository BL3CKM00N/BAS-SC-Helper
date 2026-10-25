@extends('layouts.app')

@section('body-class', 'flex flex-col')

@section('content')
    <header class="border-b border-white/10 bg-[#262623]">
        @php
            $tabs = [
                ['Dashboard', route('dashboard'), request()->routeIs('dashboard')],
                ['Reminders', route('reminders.index'), request()->routeIs('reminders.*')],
            ];
            // The Teams tab only exists for people who are in a team.
            if (auth()->user()->teams->isNotEmpty()) {
                $tabs[] = ['Teams', route('teams.index'), request()->routeIs('teams.*')];
            }
            $tabs[] = ['Settings', route('settings.edit'), request()->routeIs('settings.*')];
            if (auth()->user()->isAdmin()) {
                $tabs[] = ['Admin', '/admin', false];
            }
        @endphp

        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 px-4 py-3">
            <a href="{{ route('dashboard') }}" class="flex items-center">
                <img src="{{ $logoUrl }}" alt="BAS World" class="h-7 w-auto">
            </a>

            {{-- On a phone the menu is behind this button, from the sm breakpoint up it is always visible. --}}
            <button type="button" data-menu-button aria-expanded="false" aria-controls="main-menu"
                class="group -mr-2 flex size-10 items-center justify-center rounded-lg text-white transition hover:bg-white/5 sm:hidden">
                <span class="sr-only">Menu</span>
                <svg class="size-6 group-aria-expanded:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
                <svg class="hidden size-6 group-aria-expanded:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
            </button>

            <div id="main-menu" data-menu class="hidden basis-full flex-col gap-4 pb-1 pt-3 sm:flex sm:flex-1 sm:basis-auto sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:p-0">
                <nav class="flex flex-col gap-1 sm:flex-row" aria-label="Main">
                    @foreach ($tabs as [$label, $url, $active])
                        <a href="{{ $url }}" @if ($active) aria-current="page" @endif
                            class="whitespace-nowrap rounded-lg px-3 py-2.5 text-base font-medium transition sm:py-1.5 sm:text-sm {{ $active ? 'bg-white/10' : 'text-[#b8bab8] hover:bg-white/5 hover:text-white' }}">{{ $label }}</a>
                    @endforeach
                </nav>

                <div class="flex items-center justify-between gap-3 border-t border-white/10 pt-4 sm:border-0 sm:pt-0">
                    <span class="truncate text-sm text-[#b8bab8]">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-white/15 px-3 py-1.5 text-sm transition hover:bg-white/5">Sign out</button>
                    </form>
                </div>
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
