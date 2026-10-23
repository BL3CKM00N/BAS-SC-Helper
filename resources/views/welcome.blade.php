<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'BAS SC Helper') }}</title>
        <link rel="icon" href="/favicon.svg?v={{ filemtime(public_path('favicon.svg')) }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#1d1d1a">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen flex items-center justify-center bg-[#1d1d1a] p-6 font-sans antialiased">
        <img src="/logo.svg?v={{ filemtime(public_path('logo.svg')) }}" alt="BAS World" class="h-9 w-auto sm:h-12">
    </body>
</html>
