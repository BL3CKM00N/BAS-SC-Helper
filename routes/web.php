<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountSecurityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordSetupController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamRequestController;
use App\Http\Controllers\WorkHoursController;
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

// The login card lives on the home page. Signed-in users go straight to the dashboard.
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('auth.login');
})->name('login');

Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Called by the buttons on a push notification, authorised by the signed URL (see ReminderDue).
Route::post('/push/{recipient}/done', [PushController::class, 'done'])->middleware('signed')->name('push.done');
Route::post('/push/{recipient}/snooze', [PushController::class, 'snooze'])->middleware('signed')->name('push.snooze');

// First password, from an invitation email (signed link, valid once) or after a temporary password.
Route::get('/invite/{user}', [PasswordSetupController::class, 'inviteForm'])->middleware('signed')->name('invite.show');
Route::post('/invite/{user}', [PasswordSetupController::class, 'invite'])->middleware('signed')->name('invite.store');
Route::middleware('auth')->group(function () {
    Route::get('/password/change', [PasswordSetupController::class, 'changeForm'])->name('password.change');
    Route::post('/password/change', [PasswordSetupController::class, 'change'])->name('password.update');
});

Route::middleware(['auth', 'password.chosen'])->group(function () {
    Route::post('/devices/subscribe', [PushController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/devices/unsubscribe', [PushController::class, 'unsubscribe'])->name('push.unsubscribe');
    Route::post('/devices/test', [PushController::class, 'test'])->name('push.test');

    Route::view('/dashboard', 'dashboard.index')->name('dashboard');

    Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders.index');
    Route::get('/reminders/due', [ReminderController::class, 'due'])->name('reminders.due');
    Route::get('/reminders/create', [ReminderController::class, 'create'])->name('reminders.create');
    Route::post('/reminders', [ReminderController::class, 'store'])->name('reminders.store');
    Route::get('/reminders/{reminder}/edit', [ReminderController::class, 'edit'])->name('reminders.edit');
    Route::patch('/reminders/{reminder}', [ReminderController::class, 'update'])->name('reminders.update');
    Route::delete('/reminders/{reminder}', [ReminderController::class, 'destroy'])->name('reminders.destroy');
    Route::post('/reminders/{reminder}/done', [ReminderController::class, 'done'])->name('reminders.done');
    Route::post('/reminders/{reminder}/snooze', [ReminderController::class, 'snooze'])->name('reminders.snooze');
    Route::post('/reminders/{reminder}/skip', [ReminderController::class, 'skip'])->name('reminders.skip');

    // Your own account. /settings was the old, single page and now leads here.
    Route::redirect('/settings', '/account');
    Route::get('/account', [AccountController::class, 'index'])->name('account.index');
    Route::get('/account/profile', [AccountController::class, 'profile'])->name('account.profile');
    Route::get('/account/notifications', [AccountController::class, 'notifications'])->name('account.notifications');
    Route::get('/account/work-hours', [WorkHoursController::class, 'edit'])->name('account.work-hours');
    Route::patch('/account/work-hours', [WorkHoursController::class, 'update'])->name('account.work-hours.update');
    Route::get('/account/security', [AccountSecurityController::class, 'show'])->name('account.security');
    Route::put('/account/security/password', [AccountSecurityController::class, 'changePassword'])->name('account.security.password');
    Route::delete('/account/security/devices', [AccountSecurityController::class, 'revokeOthers'])->name('account.security.devices.destroy-others');
    Route::post('/account/security/devices/sign-out-everywhere', [AccountSecurityController::class, 'logoutEverywhere'])->name('account.security.devices.sign-out-everywhere');
    Route::delete('/account/security/devices/{handle}', [AccountSecurityController::class, 'revoke'])->where('handle', '[a-f0-9]{32}')->name('account.security.devices.destroy');

    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');
    Route::patch('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::post('/teams/{team}/requests', [TeamRequestController::class, 'store'])->name('teams.requests.store');
    Route::delete('/teams/{team}/requests/{teamRequest}', [TeamRequestController::class, 'destroy'])->name('teams.requests.destroy');
});
