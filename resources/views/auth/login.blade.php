@extends('layouts.app')

@section('title', 'Sign in - '.config('app.name', 'BAS SC Helper'))

@section('content')
    <main class="w-full max-w-sm">
        <div class="rounded-2xl border border-white/10 bg-[#262623] p-8 shadow-xl">
            <img src="{{ $logoUrl }}" alt="BAS World" class="mx-auto mb-8 h-8 w-auto">

            <h1 class="text-center text-lg font-medium">Sign in</h1>

            <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="email" class="mb-1 block text-sm text-[#b8bab8]">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                        class="w-full rounded-lg border border-white/15 bg-[#1d1d1a] px-3 py-2.5 text-sm text-white outline-none focus:border-[#1da15e] focus:ring-1 focus:ring-[#1da15e]">
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm text-[#b8bab8]">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                        class="w-full rounded-lg border border-white/15 bg-[#1d1d1a] px-3 py-2.5 text-sm text-white outline-none focus:border-[#1da15e] focus:ring-1 focus:ring-[#1da15e]">
                </div>

                @error('email')
                    <p role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-300">{{ $message }}</p>
                @enderror
                @error('password')
                    <p role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-300">{{ $message }}</p>
                @enderror

                <label class="flex items-center gap-2 text-sm text-[#b8bab8]">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-4 rounded border-white/20 bg-[#1d1d1a] accent-[#1da15e]">
                    Stay signed in
                </label>

                <button type="submit" class="w-full rounded-lg bg-[#1da15e] px-4 py-2.5 text-sm font-medium text-white transition hover:bg-[#178a4f]">
                    Sign in
                </button>
            </form>
        </div>
    </main>
@endsection
