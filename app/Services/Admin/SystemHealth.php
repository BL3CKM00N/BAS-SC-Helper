<?php

namespace App\Services\Admin;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Small signals that show whether the background work of the app is alive.
 */
class SystemHealth
{
    /** The scheduler runs every minute; this long without a beat means it stopped. */
    public const STALE_AFTER_MINUTES = 3;

    public function beat(): void
    {
        Cache::forever('health.scheduler', now()->toIso8601String());
    }

    public function lastBeat(): ?CarbonInterface
    {
        $value = Cache::get('health.scheduler');

        return $value ? now()->parse($value) : null;
    }

    public function schedulerIsAlive(): bool
    {
        $beat = $this->lastBeat();

        return $beat !== null && $beat->gt(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    public function pushFailed(string $reason): void
    {
        Cache::forever('health.push_failed', [
            'at' => now()->toIso8601String(),
            'reason' => $reason,
            'count' => (int) (Cache::get('health.push_failed')['count'] ?? 0) + 1,
        ]);
    }

    /**
     * @return array{at: CarbonInterface, reason: string, count: int}|null
     */
    public function lastPushFailure(): ?array
    {
        $value = Cache::get('health.push_failed');

        return $value ? ['at' => now()->parse($value['at']), 'reason' => $value['reason'], 'count' => $value['count']] : null;
    }

    public function databaseIsUp(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
