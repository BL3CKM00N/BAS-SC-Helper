@extends('layouts.app')

@section('title', 'Maintenance - '.config('app.name', 'BAS SC Helper'))
@section('body-attrs')data-maintenance="1"@endsection

@section('content')
    <main class="flex max-w-md flex-col items-center text-center">
        <img src="{{ $logoUrl }}" alt="BAS World" class="mb-10 h-8 w-auto">
        <h1 class="text-xl font-medium">Under maintenance</h1>
        <p class="mt-2 text-sm leading-6 text-[#b8bab8]">{{ $text }}</p>
        <p class="mt-1 text-sm leading-6 text-[#b8bab8]">Your reminders keep working and notifications still arrive. This page opens the app again by itself when we are done.</p>
        <form method="POST" action="{{ route('logout') }}" class="mt-8">
            @csrf
            <button type="submit" class="rounded-lg border border-white/15 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-white/5">Sign out</button>
        </form>
    </main>
@endsection
