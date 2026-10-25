<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_id', 'requested_by', 'type', 'target_user_id', 'name', 'email', 'note', 'status', 'decided_by', 'decided_at', 'decision_note'])]
class TeamRequest extends Model
{
    use HasFactory;

    public const NEW_MEMBER = 'new_member';

    public const ADD_MEMBER = 'add_member';

    public const REMOVE_MEMBER = 'remove_member';

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
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
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /**
     * A short sentence about what is being asked, for lists and notifications.
     */
    public function summary(): string
    {
        return match ($this->type) {
            self::NEW_MEMBER => 'New account for '.$this->name.' ('.$this->email.')',
            self::ADD_MEMBER => 'Add '.($this->target?->name ?? $this->email).' to the team',
            self::REMOVE_MEMBER => 'Remove '.($this->target?->name ?? 'a member').' from the team',
            default => $this->type,
        };
    }
}
