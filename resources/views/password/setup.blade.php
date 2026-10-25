@extends('layouts.app')

@section('title', $title.' - '.config('app.name', 'BAS SC Helper'))

@section('content')
    <main class="w-full max-w-sm">
        <div class="rounded-2xl border border-white/10 bg-[#262623] p-8 shadow-xl">
            <img src="{{ $logoUrl }}" alt="BAS World" class="mx-auto mb-8 h-8 w-auto">

            <h1 class="text-center text-lg font-medium">{{ $title }}</h1>
            <p class="mt-2 text-center text-sm text-[#b8bab8]">{{ $intro }}</p>

            <form method="POST" action="{{ $action }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="password" class="mb-1 block text-sm text-[#b8bab8]">New password</label>
                    <input id="password" name="password" type="password" required autofocus autocomplete="new-password" minlength="10"
                        class="w-full rounded-lg border border-white/15 bg-[#1d1d1a] px-3 py-2.5 text-sm text-white outline-none focus:border-[#1da15e] focus:ring-1 focus:ring-[#1da15e]">
                    <p class="mt-1 text-xs text-[#b8bab8]">At least 10 characters.</p>
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm text-[#b8bab8]">Repeat the password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                        class="w-full rounded-lg border border-white/15 bg-[#1d1d1a] px-3 py-2.5 text-sm text-white outline-none focus:border-[#1da15e] focus:ring-1 focus:ring-[#1da15e]">
                </div>

                @error('password')
                    <p role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-300">{{ $message }}</p>
                @enderror

                <button type="submit" class="w-full rounded-lg bg-[#1da15e] px-4 py-2.5 text-sm font-medium text-white transition hover:bg-[#178a4f]">Save password</button>
            </form>
        </div>
    </main>
@endsection
