@extends('layouts.app')

@section('title', 'Dashboard - '.config('app.name', 'BAS SC Helper'))
@section('body-class', 'flex flex-col')

@section('content')
    <header class="border-b border-white/10 bg-[#262623]">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-3 px-4 py-3">
            <a href="{{ route('dashboard') }}" class="flex items-center">
                <img src="{{ $logoUrl }}" alt="BAS World" class="h-7 w-auto">
            </a>

            <nav class="order-last flex w-full gap-1 sm:order-none sm:w-auto" aria-label="Main">
                <a href="{{ route('dashboard') }}" aria-current="page" class="rounded-lg bg-white/10 px-3 py-1.5 text-sm font-medium">Dashboard</a>
                @if (auth()->user()->isAdmin())
                    <a href="/admin" class="rounded-lg px-3 py-1.5 text-sm font-medium text-[#b8bab8] transition hover:bg-white/5 hover:text-white">Admin</a>
                @endif
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
        <h1 class="text-2xl font-semibold">Welcome, {{ auth()->user()->name }}</h1>
        <p class="mt-1 text-sm text-[#b8bab8]">This dashboard is a placeholder. The service coordinator tools will appear here.</p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (['Today', 'Open tasks', 'Notes'] as $card)
                <section class="rounded-2xl border border-dashed border-white/15 bg-[#262623] p-6">
                    <h2 class="text-sm font-medium text-[#b8bab8]">{{ $card }}</h2>
                    <p class="mt-6 text-sm text-[#b8bab8]/70">Coming soon</p>
                </section>
            @endforeach
        </div>
    </main>
@endsection
