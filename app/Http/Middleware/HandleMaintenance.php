<?php

namespace App\Http\Middleware;

use App\Services\Admin\Maintenance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While maintenance mode is on, signed-in people who are not admins only see the maintenance page.
 * Guests can still reach the login page (it shows a notice), so admins can sign in.
 */
class HandleMaintenance
{
    public function __construct(private readonly Maintenance $maintenance) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $this->maintenance->isOn() || $user === null || $user->isAdmin()) {
            return $next($request);
        }

        $text = $this->maintenance->textFor($user);

        if ($request->is('logout')) {
            return $next($request);
        }

        $retryAfter = $this->maintenance->backAt()?->isFuture() ? (int) now()->diffInSeconds($this->maintenance->backAt()) : 300;

        return response()->view('maintenance', ['text' => $text], 503)->header('Retry-After', (string) $retryAfter);
    }
}
