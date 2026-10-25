@extends('account.layout')

@section('account-title', 'Notifications')

@section('account')
    <section class="rounded-2xl border border-white/10 bg-[#262623] p-6" data-push-panel>
        <h2 class="text-sm font-medium text-[#b8bab8]">Notifications</h2>
        <p class="mt-1 max-w-2xl text-sm text-[#b8bab8]">Get your reminders as a notification on this device, also when the app is closed. Turn it on for each phone or computer you use.</p>
        <p class="mt-4 text-sm" data-push-status role="status">Checking this device...</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" data-push-enable hidden class="rounded-lg bg-[#1da15e] px-4 py-2.5 text-sm font-medium transition hover:bg-[#178a4f]">Turn on notifications</button>
            <button type="button" data-push-test hidden class="rounded-lg border border-white/15 px-4 py-2.5 text-sm transition hover:bg-white/5">Send a test</button>
            <button type="button" data-push-disable hidden class="rounded-lg border border-white/15 px-4 py-2.5 text-sm text-red-300 transition hover:bg-red-500/10">Turn off</button>
        </div>
    </section>
@endsection
