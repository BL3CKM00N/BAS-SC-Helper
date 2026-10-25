@extends('layouts.shell')

@section('title', 'Dashboard - '.config('app.name', 'BAS SC Helper'))

@section('page')
    <div data-notify-hint hidden role="note" class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-400/30 bg-amber-400/10 px-5 py-4">
        <p class="max-w-2xl text-sm text-amber-100">Reminders only reach you when notifications are on. Turn them on for this device so you do not miss one.</p>
        <div class="flex items-center gap-2">
            <a href="{{ route('account.notifications') }}" class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-medium text-[#1d1d1a] transition hover:bg-amber-300">Turn on</a>
            <button type="button" data-notify-hint-dismiss class="rounded-lg px-3 py-2 text-sm text-amber-100 transition hover:bg-white/5">Not now</button>
        </div>
    </div>

    <h1 class="text-2xl font-semibold">Welcome, {{ auth()->user()->name }}</h1>
    <p class="mt-1 text-sm text-[#b8bab8]">This dashboard is a placeholder. The service coordinator tools will appear here.</p>

    <section class="mt-8 rounded-2xl border border-white/10 bg-[#262623] p-6" aria-labelledby="due-heading">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 id="due-heading" class="text-sm font-medium text-[#b8bab8]">Due now</h2>
            <a href="{{ route('reminders.index') }}" class="text-sm text-[#7be0a8] hover:underline">All reminders</a>
        </div>
        <div class="space-y-3" data-due-now>
            @include('reminders._due', ['due' => app(\App\Services\Reminders\ReminderEngine::class)->dueNow(auth()->user()), 'engine' => app(\App\Services\Reminders\ReminderEngine::class)])
        </div>
    </section>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach (['Today', 'Open tasks', 'Notes'] as $card)
            <section class="rounded-2xl border border-dashed border-white/15 bg-[#262623] p-6">
                <h2 class="text-sm font-medium text-[#b8bab8]">{{ $card }}</h2>
                <p class="mt-6 text-sm text-[#b8bab8]/70">Coming soon</p>
            </section>
        @endforeach
    </div>
@endsection
