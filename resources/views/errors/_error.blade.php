@extends('layouts.app')

@section('title', $code.' - '.config('app.name', 'BAS SC Helper'))

@section('content')
    <main class="flex max-w-md flex-col items-center text-center">
        <img src="/logo.svg" alt="BAS World" class="mb-10 h-8 w-auto">
        <p class="text-6xl font-semibold tracking-tight text-[#1da15e]">{{ $code }}</p>
        <h1 class="mt-4 text-xl font-medium">{{ $title }}</h1>
        <p class="mt-2 text-sm leading-6 text-[#b8bab8]">{{ $message }}</p>
        <a href="/" class="mt-8 rounded-lg bg-[#1da15e] px-5 py-2.5 text-sm font-medium text-white transition hover:bg-[#178a4f]">Back to home</a>
    </main>
@endsection
