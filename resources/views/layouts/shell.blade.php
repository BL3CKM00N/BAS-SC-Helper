@extends('layouts.app')

@section('body-class', 'flex flex-col')

@section('content')
    <header class="border-b border-white/10 bg-[#262623]">
        @php
            $user = auth()->user();
            // The work pages. Everything about you lives in the profile menu.
            $tabs = [
                ['Dashboard', route('dashboard'), request()->routeIs('dashboard')],
                ['Reminders', route('reminders.index'), request()->routeIs('reminders.*')],
            ];
            // The Teams tab only exists for people who are in a team.
            if ($user->teams->isNotEmpty()) {
                $tabs[] = ['Teams', route('teams.index'), request()->routeIs('teams.*')];
            }
        @endphp

        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 px-4 py-3">
            <a href="{{ route('dashboard') }}" class="flex items-center">
                <img src="{{ $logoUrl }}" alt="BAS World" class="h-7 w-auto">
            </a>

            {{-- On a phone the menu is behind this button, from the sm breakpoint up it is always visible. --}}
            <button type="button" data-menu-button aria-expanded="false" aria-controls="main-menu"
                class="group relative -mr-2 flex size-10 items-center justify-center rounded-lg text-white transition hover:bg-white/5 sm:hidden">
                <span class="sr-only">Menu</span>
                <svg class="size-6 group-aria-expanded:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
                <svg class="hidden size-6 group-aria-expanded:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                <span data-notify-dot hidden class="absolute right-1.5 top-1.5 size-2.5 rounded-full bg-amber-400 ring-2 ring-[#262623]"></span>
            </button>

            <div id="main-menu" data-menu class="hidden basis-full flex-col gap-4 pb-1 pt-3 sm:flex sm:flex-1 sm:basis-auto sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:p-0">
                <nav class="flex flex-col gap-1 sm:flex-row" aria-label="Main">
                    @foreach ($tabs as [$label, $url, $active])
                        <a href="{{ $url }}" @if ($active) aria-current="page" @endif
                            class="whitespace-nowrap rounded-lg px-3 py-2.5 text-base font-medium transition sm:py-1.5 sm:text-sm {{ $active ? 'bg-white/10' : 'text-[#b8bab8] hover:bg-white/5 hover:text-white' }}">{{ $label }}</a>
                    @endforeach
                </nav>

                {{-- The profile menu on a computer: a dropdown under the round button. --}}
                <div class="relative hidden sm:block" data-profile>
                    <button type="button" data-profile-button aria-expanded="false" aria-controls="profile-menu" aria-haspopup="true"
                        class="group flex items-center gap-2 rounded-full py-1 pl-1 pr-3 text-sm transition hover:bg-white/5">
                        <span class="relative flex size-8 items-center justify-center rounded-full bg-[#1da15e] text-xs font-semibold text-white">
                            {{ $user->initials() }}
                            <span data-notify-dot hidden class="absolute -right-0.5 -top-0.5 size-2.5 rounded-full bg-amber-400 ring-2 ring-[#262623]"></span>
                        </span>
                        <span class="max-w-[10rem] truncate">{{ $user->name }}</span>
                        <svg class="size-4 text-[#b8bab8] transition group-aria-expanded:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 8l5 5 5-5" /></svg>
                    </button>

                    <div id="profile-menu" hidden class="absolute right-0 z-40 mt-2 w-72 rounded-xl border border-white/10 bg-[#262623] p-2 shadow-2xl">
                        @include('layouts._account-menu', ['variant' => 'dropdown'])
                    </div>
                </div>

                {{-- The same on a phone: one row at the bottom of the hamburger menu that opens the account links. --}}
                <div class="border-t border-white/10 pt-3 sm:hidden">
                    @php($onAccountPage = request()->routeIs('account.*'))
                    <button type="button" data-account-toggle aria-expanded="false" aria-controls="account-section"
                        class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition hover:bg-white/5 {{ $onAccountPage ? 'bg-white/10' : '' }}">
                        <span class="relative flex size-9 shrink-0 items-center justify-center rounded-full bg-[#1da15e] text-xs font-semibold text-white">
                            {{ $user->initials() }}
                            <span data-notify-dot hidden class="absolute -right-0.5 -top-0.5 size-2.5 rounded-full bg-amber-400 ring-2 ring-[#262623]"></span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ $user->name }}</span>
                            <span class="block truncate text-xs text-[#b8bab8]">{{ $user->email }}</span>
                        </span>
                        <svg class="size-5 shrink-0 text-[#b8bab8] transition group-aria-expanded:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 8l5 5 5-5" /></svg>
                    </button>

                    <div id="account-section" hidden class="mt-1">
                        @include('layouts._account-menu', ['variant' => 'section'])
                    </div>
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
