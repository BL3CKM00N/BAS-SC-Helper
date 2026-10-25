<?php

namespace App\Http\Middleware;

use App\Services\Admin\Maintenance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a header to the health check while maintenance mode is on. The connection monitor in the browser polls
 * that URL every second and, for people who are locked out, covers the page with a notice. The health check has
 * no session, so it cannot tell who is asking: the page decides whether the notice applies.
 */
class AnnounceMaintenance
{
    public function __construct(private readonly Maintenance $maintenance) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('up') && $this->maintenance->isOn()) {
            $response->headers->set('X-Maintenance', rawurlencode($this->maintenance->textFor(null)));
        }

        return $response;
    }
}
