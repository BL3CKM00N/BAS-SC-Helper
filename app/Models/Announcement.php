<?php

namespace App\Models;

use App\Observers\AnnouncementObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

#[ObservedBy(AnnouncementObserver::class)]
#[Fillable(['title', 'body', 'severity', 'audience', 'team_id', 'starts_at', 'ends_at', 'push', 'pushed_at', 'created_by'])]
class Announcement extends Model
{
    public const INFO = 'info';

    public const WARNING = 'warning';

    public const CRITICAL = 'critical';

    public const EVERYONE = 'everyone';

    public const TEAM = 'team';

    public const MANAGERS = 'managers';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'pushed_at' => 'datetime',
            'push' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function dismissedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_dismissals')->withPivot('created_at');
    }

    /**
     * Critical announcements stay until they end: people cannot dismiss them.
     */
    public function isDismissable(): bool
    {
        return $this->severity !== self::CRITICAL;
    }

    public function isActive(): bool
    {
        return ($this->starts_at === null || $this->starts_at->isPast())
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /**
     * Announcements meant for this person: for everyone, for one of their teams, or for team managers.
     *
     * @param  Builder<static>  $query
     */
    public function scopeFor(Builder $query, User $user): void
    {
        $query->where(function (Builder $q) use ($user) {
            $q->where('audience', self::EVERYONE)
                ->orWhere(fn (Builder $q) => $q->where('audience', self::TEAM)->whereIn('team_id', $user->teams()->select('teams.id')))
                ->when($user->isTeamManager(), fn (Builder $q) => $q->orWhere('audience', self::MANAGERS));
        });
    }

    /**
     * What a person sees right now: active, meant for them and not dismissed, critical ones first.
     *
     * @return Collection<int, static>
     */
    public static function visibleFor(User $user): Collection
    {
        return static::query()->active()->for($user)
            ->whereDoesntHave('dismissedBy', fn (Builder $q) => $q->whereKey($user->getKey()))
            ->latest()
            ->get()
            ->sortBy(fn (self $a) => match ($a->severity) {
                self::CRITICAL => 0,
                self::WARNING => 1,
                default => 2,
            })
            ->values();
    }

    /**
     * The people this announcement is for, for the optional push notification (accounts that are off are skipped).
     *
     * @return Builder<User>
     */
    public function recipients(): Builder
    {
        return User::query()->whereNull('disabled_at')->when($this->audience === self::TEAM, fn (Builder $q) => $q->whereHas('teams', fn (Builder $t) => $t->whereKey($this->team_id)))
            ->when($this->audience === self::MANAGERS, fn (Builder $q) => $q->whereHas('role', fn (Builder $r) => $r->where('name', Role::TEAM_MANAGER)));
    }
}
