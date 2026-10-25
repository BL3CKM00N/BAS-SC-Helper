<?php

namespace App\Services\Account;

use App\Models\User;
use App\Support\DeviceInfo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The places a user is signed in, read from and ended in the `sessions` table.
 *
 * A session id is a secret (whoever has it is signed in), so pages only ever see a "handle": a keyed hash
 * of the id that can point at a session but cannot be used as one.
 */
class UserSessions
{
    /**
     * @return Collection<int, array{handle: string, current: bool, browser: string, os: string, device: string, label: string, ip: ?string, last_active: CarbonImmutable}>
     */
    public function for(User $user, string $currentId): Collection
    {
        return DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($row) => DeviceInfo::parse($row->user_agent) + [
                'handle' => $this->handle($row->id),
                'current' => hash_equals($currentId, $row->id),
                'ip' => $row->ip_address,
                'last_active' => CarbonImmutable::createFromTimestampUTC($row->last_activity),
            ])
            ->sortByDesc('current')
            ->values();
    }

    public function handle(string $sessionId): string
    {
        return substr(hash_hmac('sha256', $sessionId, (string) config('app.key')), 0, 32);
    }

    /**
     * End one session of this user. Returns false when the user has no session with that handle.
     */
    public function revoke(User $user, string $handle, string $currentId): bool
    {
        $id = DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->pluck('id')
            ->first(fn (string $id) => hash_equals($this->handle($id), $handle));

        if ($id === null || hash_equals($currentId, $id)) {
            return false;
        }

        DB::table('sessions')->where('id', $id)->where('user_id', $user->getKey())->delete();

        return true;
    }

    /**
     * End every session except the one with this id. Returns how many were ended.
     */
    public function revokeOthers(User $user, string $currentId): int
    {
        return DB::table('sessions')->where('user_id', $user->getKey())->where('id', '!=', $currentId)->delete();
    }

    public function revokeAll(User $user): int
    {
        return DB::table('sessions')->where('user_id', $user->getKey())->delete();
    }

    /**
     * Every signed-in session of every user, for the admin panel. Keyed by handle, never by session id.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public function everyone(string $currentId): Collection
    {
        $users = User::query()->whereIn('id', DB::table('sessions')->whereNotNull('user_id')->pluck('user_id'))->get()->keyBy('id');

        return DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>', now()->subMinutes((int) config('session.lifetime'))->getTimestamp())
            ->orderByDesc('last_activity')
            ->get()
            ->mapWithKeys(fn ($row) => [$this->handle($row->id) => DeviceInfo::parse($row->user_agent) + [
                'user_id' => $row->user_id,
                'user' => $users[$row->user_id]->name ?? 'Unknown',
                'email' => $users[$row->user_id]->email ?? '',
                'current' => hash_equals($currentId, $row->id),
                'ip' => $row->ip_address,
                'last_active' => CarbonImmutable::createFromTimestampUTC($row->last_activity),
            ]]);
    }

    /**
     * End any session by its handle (admin use). Returns the user it belonged to, or null when it no longer exists.
     */
    public function revokeHandle(string $handle, string $currentId): ?User
    {
        $row = DB::table('sessions')
            ->whereNotNull('user_id')
            ->get(['id', 'user_id'])
            ->first(fn ($row) => hash_equals($this->handle($row->id), $handle));

        if ($row === null || hash_equals($currentId, $row->id)) {
            return null;
        }

        DB::table('sessions')->where('id', $row->id)->delete();

        return User::query()->find($row->user_id);
    }

    /**
     * Invalidate every "stay signed in" cookie of the user.
     */
    public function rotateRememberToken(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
