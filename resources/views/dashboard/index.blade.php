@extends('layouts.shell')

@section('title', 'Dashboard - '.config('app.name', 'BAS SC Helper'))

@section('page')
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
@endsection
