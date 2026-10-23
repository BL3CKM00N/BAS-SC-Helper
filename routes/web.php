<?php

use Illuminate\Support\Facades\Route;

Route::get('/sw.js', function () {
    return response()->file(resource_path('sw.js'), [
        'Content-Type' => 'application/javascript',
        'Cache-Control' => 'no-cache, must-revalidate',
        'Service-Worker-Allowed' => '/',
    ]);
});

Route::get('/', function () {
    return view('welcome');
});
