<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-app="bas"@if (app()->environment('local')) data-env="local"@endif>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', config('app.name', 'BAS SC Helper')){{ app()->environment('local') ? ' [DEV]' : '' }}</title>
        <meta name="app-logo" content="{{ $logoUrl }}">
        <link rel="icon" href="/favicon.svg?v={{ filemtime(public_path('favicon.svg')) }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#1d1d1a">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#1d1d1a] font-sans text-white antialiased @yield('body-class', 'flex items-center justify-center p-6')">
        @yield('content')

        @if (app()->environment('local'))
            <footer class="fixed inset-x-0 bottom-0 py-2 text-center font-[system-ui] text-xs font-extrabold uppercase tracking-widest text-red-500">
                Development environment
            </footer>
        @endif
    </body>
</html>
