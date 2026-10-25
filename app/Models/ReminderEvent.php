<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reminder_id', 'user_id', 'type', 'meta', 'created_at'])]
class ReminderEvent extends Model
{
    public const UPDATED_AT = null;

    public const NOTIFIED = 'notified';

    public const DONE = 'done';

    public const SNOOZED = 'snoozed';

    public const SKIPPED = 'skipped';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Reminder, $this>
     */
    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }
}
