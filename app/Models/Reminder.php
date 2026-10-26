<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'created_by', 'team_id', 'assignee_id', 'title', 'notes', 'type', 'due_at', 'lead_minutes', 'nag_minutes',
    'interval_minutes', 'daily_target', 'recurrence', 'weekdays', 'month_days', 'due_time', 'completion_mode', 'ignore_work_hours_when_overdue', 'completed_at', 'archived_at',
])]
class Reminder extends Model
{
    use HasFactory;

    public const DEADLINE = 'deadline';

    public const REPEATING = 'repeating';

    public const WEEKLY = 'weekly';

    public const MONTHLY = 'monthly';

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

    /**
     * A recurring reminder that is planned per day: it has chosen weekdays or days of the month, or it comes
     * once a day. The others (every work day, every X minutes) run on a rolling timer.
     */
    public function followsDays(): bool
    {
        return $this->isRepeating() && ($this->recurrence !== null || $this->interval_minutes === null);
    }

    /**
     * @return list<int>
     */
    public function weekdayList(): array
    {
        return $this->numbers($this->weekdays);
    }

    /**
     * Days of the month, -1 being the last day.
     *
     * @return list<int>
     */
    public function monthDayList(): array
    {
        return $this->numbers($this->month_days);
    }

    /**
     * The schedule in words: "Every Mon, Wed, Fri", "Monthly on the 1st and last day, every 90 min".
     */
    public function scheduleLabel(): string
    {
        return $this->daysLabel()
            .($this->interval_minutes ? ', every '.$this->interval_minutes.' min' : '')
            .($this->due_time ? ', due '.$this->due_time : '');
    }

    private function daysLabel(): string
    {
        if ($this->recurrence === self::MONTHLY) {
            $days = collect($this->monthDayList())->sortBy(fn (int $day) => $day === -1 ? 99 : $day)->map(
                fn (int $day) => $day === -1 ? 'last day' : $day.(in_array($day % 100, [11, 12, 13], true) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$day % 10] ?? 'th'))
            )->values();

            return 'Monthly on the '.($days->count() > 1 ? $days->slice(0, -1)->implode(', ').' and '.$days->last() : $days->first());
        }

        if ($this->recurrence === null) {
            return 'Every work day';
        }

        $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
        $days = $this->weekdayList();

        if ($days === [1, 2, 3, 4, 5, 6, 7]) {
            return 'Every day';
        }

        return 'Every '.collect($days)->map(fn (int $day) => $names[$day] ?? '')->implode(', ');
    }

    /**
     * @return list<int>
     */
    private function numbers(?string $list): array
    {
        $numbers = array_map('intval', array_filter(explode(',', (string) $list), fn ($value) => $value !== ''));
        sort($numbers);

        return array_values(array_unique($numbers));
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
