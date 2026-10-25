<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'created_by', 'team_id', 'assignee_id', 'title', 'notes', 'type', 'due_at', 'lead_minutes', 'nag_minutes',
    'interval_minutes', 'daily_target', 'completion_mode', 'ignore_work_hours_when_overdue', 'completed_at', 'archived_at',
])]
class Reminder extends Model
{
    use HasFactory;

    public const DEADLINE = 'deadline';

    public const REPEATING = 'repeating';

    public const ANYONE = 'anyone';

    public const EACH = 'each';

    /**
     * The same defaults as the database, so a reminder that was just created has them too.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'lead_minutes' => 240,
        'completion_mode' => self::ANYONE,
        'ignore_work_hours_when_overdue' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
            'ignore_work_hours_when_overdue' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return HasMany<ReminderRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(ReminderRecipient::class);
    }

    /**
     * @return HasMany<ReminderEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ReminderEvent::class);
    }

    public function isDeadline(): bool
    {
        return $this->type === self::DEADLINE;
    }

    public function isRepeating(): bool
    {
        return $this->type === self::REPEATING;
    }

    public function isPersonal(): bool
    {
        return $this->team_id === null;
    }

    public function isActive(): bool
    {
        return $this->archived_at === null && $this->completed_at === null;
    }
}
