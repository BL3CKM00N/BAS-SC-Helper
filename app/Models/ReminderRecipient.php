<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reminder_id', 'user_id', 'next_notify_at', 'pending_since', 'last_notified_at', 'snoozed_until', 'skipped_until', 'completed_at',
])]
class ReminderRecipient extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_notify_at' => 'datetime',
            'pending_since' => 'datetime',
            'last_notified_at' => 'datetime',
            'snoozed_until' => 'datetime',
            'skipped_until' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Reminder, $this>
     */
    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
