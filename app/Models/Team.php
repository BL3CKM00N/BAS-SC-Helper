<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'members_can_create'])]
class Team extends Model
{
    use HasFactory;

    public const MANAGER = 'manager';

    public const MEMBER = 'member';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['members_can_create' => 'boolean'];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /**
     * @return HasMany<Reminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->users()->whereKey($user->getKey())->exists();
    }

    public function isManagedBy(User $user): bool
    {
        return $this->users()->whereKey($user->getKey())->wherePivot('role', self::MANAGER)->exists();
    }
}
