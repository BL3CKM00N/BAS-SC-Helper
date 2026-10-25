<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The audit log: who did what, and when. Everything an admin or a team manager changes writes a line here.
 */
final class Audit
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function record(string $action, string $description, ?Model $subject = null, array $meta = [], ?User $actor = null): AuditLog
    {
        $actor ??= Auth::user();

        return AuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'created_at' => now(),
        ]);
    }
}
