<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['actor_id', 'actor_name', 'action', 'description', 'subject_type', 'subject_id', 'meta', 'ip_address', 'created_at'])]
class AuditLog extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    /** How long entries are kept. */
    public const KEEP_DAYS = 400;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Entries older than the retention period are removed by `model:prune`.
     *
     * @return Builder<static>
     */
    public function prunable()
    {
        return static::query()->where('created_at', '<', now()->subDays(self::KEEP_DAYS));
    }

    /**
     * The first part of the action ("user" in "user.role_changed"): the area it belongs to.
     */
    public function area(): string
    {
        return strstr($this->action, '.', true) ?: $this->action;
    }
}
