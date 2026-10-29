<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Observers\UserObserver;
use App\Services\Admin\AppDefaults;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable(['name', 'email', 'password', 'role_id', 'timezone', 'work_days', 'work_start', 'work_end', 'must_change_password', 'invited_at', 'disabled_at', 'last_login_at', 'last_login_ip'])]
#[Hidden(['password', 'remember_token'])]
#[ObservedBy(UserObserver::class)]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

    /**
     * The same defaults as the database, so a user that was just created has them too.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'timezone' => 'Europe/Amsterdam',
        'work_days' => '1,2,3,4,5',
        'work_start' => '08:00',
        'work_end' => '17:30',
    ];

    /**
     * New accounts start with the defaults an admin chose (work hours and time zone).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = array_merge($this->attributes, app(AppDefaults::class)->forUser());

        parent::__construct($attributes);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && ! $this->isDisabled();
    }

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    /**
     * The person's own folders on the reminders page.
     *
     * @return HasMany<Folder, $this>
     */
    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    /**
     * Put a reminder in one of this person's folders (or take it out of all of them with null).
     */
    public function fileReminder(Reminder $reminder, ?int $folderId): void
    {
        $mine = $this->folders()->pluck('id');

        $reminder->folders()->detach($mine);

        if ($folderId && $mine->contains($folderId)) {
            $reminder->folders()->attach($folderId);
        }
    }

    /**
     * A moment shown in this user's own time zone.
     */
    public function local(?CarbonInterface $moment): ?CarbonImmutable
    {
        return $moment ? CarbonImmutable::instance($moment)->setTimezone($this->timezone ?: 'Europe/Amsterdam') : null;
    }

    /**
     * The team this user manages, if any. A manager manages exactly one team.
     *
     * @return HasOne<Team, $this>
     */
    public function managedTeam(): HasOne
    {
        return $this->hasOne(Team::class, 'manager_id');
    }

    public function isTeamManager(): bool
    {
        return $this->role?->name === Role::TEAM_MANAGER;
    }

    /**
     * Up to two letters for the round profile button: the first letters of the first and the last name.
     */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return '?';
        }

        $letters = mb_substr($words[0], 0, 1).(count($words) > 1 ? mb_substr(end($words), 0, 1) : '');

        return mb_strtoupper($letters);
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === Role::ADMIN;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invited_at' => 'datetime',
            'must_change_password' => 'boolean',
            'disabled_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
