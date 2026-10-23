<?php

use Illuminate\Support\Facades\Route;

Route::get('/sw.js', function () {
    // The version is a hash of the files the service worker precaches.
    $files = ['offline.html', 'logo.svg', 'favicon.svg', 'icons/icon-192.png'];
    $version = substr(md5(collect($files)->map(fn ($file) => md5_file(public_path($file)))->implode('')), 0, 10);

    $script = str_replace(
        ['__VERSION__', '__DEV__'],
        [$version, app()->environment('local') ? 'true' : 'false'],
        file_get_contents(resource_path('sw.js')),
    );

    return response($script, 200, [
        'Content-Type' => 'application/javascript',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Service-Worker-Allowed' => '/',
    ]);
});

Route::get('/logo-dev.svg', function () {
    abort_unless(app()->environment('local'), 404);

    // The normal logo with a small red "DEV" label next to it.
    $svg = str_replace(
        ['viewBox="0 0 583.9 68"', '</svg>'],
        [
            'viewBox="0 0 690 68"',
            '<text x="604" y="46" fill="#ef4444" font-family="system-ui, sans-serif" font-size="30" font-weight="800" letter-spacing="3">DEV</text></svg>',
        ],
        file_get_contents(public_path('logo.svg')),
    );

    return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'no-cache']);
});

Route::get('/', function () {
    return view('welcome');
});
