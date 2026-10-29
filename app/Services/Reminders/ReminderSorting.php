<?php

namespace App\Services\Reminders;

use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The sort choices on the reminders page: for reminders, the done list and the folders. The choice is
 * remembered in a cookie, so it belongs to the device and the page needs no redirect to apply it.
 */
class ReminderSorting
{
    public const COOKIE = 'reminder_sort';

    public const REMINDERS = [
        'next' => 'Next reminder',
        'deadline' => 'Deadline',
        'title' => 'Title A to Z',
        'title_desc' => 'Title Z to A',
        'newest' => 'Newest first',
        'oldest' => 'Oldest first',
        'changed' => 'Recently changed',
        'type' => 'Type',
        'team' => 'Team',
    ];

    public const DONE = [
        'recent' => 'Recently done',
        'title' => 'Title A to Z',
    ];

    public const FOLDERS = [
        'name' => 'Name A to Z',
        'due' => 'Most due now',
        'count' => 'Most reminders',
        'manual' => 'My own order',
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const OPTIONS = ['sort' => self::REMINDERS, 'done' => self::DONE, 'folders' => self::FOLDERS];

    /**
     * @var array<string, string>
     */
    public readonly array $chosen;

    /**
     * What to remember when the person picked something new on this request, else null.
     */
    public readonly ?string $remember;

    public function __construct(Request $request)
    {
        $saved = json_decode((string) $request->cookie(self::COOKIE), true);
        $saved = is_array($saved) ? $saved : [];
        $chosen = [];
        $changed = false;

        foreach (self::OPTIONS as $field => $options) {
            $default = array_key_first($options);
            $asked = $request->query($field);

            if (is_string($asked) && array_key_exists($asked, $options)) {
                $changed = $changed || ($saved[$field] ?? $default) !== $asked;
                $chosen[$field] = $asked;
            } else {
                $value = $saved[$field] ?? null;
                $chosen[$field] = is_string($value) && array_key_exists($value, $options) ? $value : $default;
            }
        }

        $this->chosen = $chosen;
        $this->remember = $changed ? json_encode($chosen) : null;
    }

    /**
     * @param  Collection<int, Reminder>  $reminders
     * @return Collection<int, Reminder>
     */
    public function reminders(Collection $reminders): Collection
    {
        $sorted = $reminders->sort(fn (Reminder $a, Reminder $b) => $this->key($a) <=> $this->key($b))->values();

        return $this->chosen['sort'] === 'title_desc' ? $sorted->reverse()->values() : $sorted;
    }

    /**
     * @param  Collection<int, Reminder>  $reminders
     * @return Collection<int, Reminder>
     */
    public function done(Collection $reminders): Collection
    {
        return $this->chosen['done'] === 'title'
            ? $reminders->sortBy(fn (Reminder $r) => mb_strtolower($r->title))->values()
            : $reminders->sortByDesc('completed_at')->values();
    }

    /**
     * @param  Collection<int, \App\Models\Folder>  $folders
     * @return Collection<int, \App\Models\Folder>
     */
    public function folders(Collection $folders): Collection
    {
        return $folders->sort(fn ($a, $b) => match ($this->chosen['folders']) {
            'due' => [-$a->due_count, mb_strtolower($a->name)] <=> [-$b->due_count, mb_strtolower($b->name)],
            'count' => [-($a->items->count() + $a->due_count), mb_strtolower($a->name)] <=> [-($b->items->count() + $b->due_count), mb_strtolower($b->name)],
            'manual' => [$a->position, mb_strtolower($a->name)] <=> [$b->position, mb_strtolower($b->name)],
            default => mb_strtolower($a->name) <=> mb_strtolower($b->name),
        })->values();
    }

    /**
     * @return array<int, int|string>
     */
    private function key(Reminder $reminder): array
    {
        $next = ($reminder->recipients->first()?->next_notify_at ?? $reminder->due_at ?? now()->addYear())->getTimestamp();

        return match ($this->chosen['sort']) {
            'deadline' => [$reminder->due_at ? 0 : 1, $reminder->due_at?->getTimestamp() ?? 0, $next],
            'title', 'title_desc' => [mb_strtolower($reminder->title), $next],
            'newest' => [-$reminder->created_at->getTimestamp(), $next],
            'oldest' => [$reminder->created_at->getTimestamp(), $next],
            'changed' => [-$reminder->updated_at->getTimestamp(), $next],
            'type' => [$reminder->isDeadline() ? 0 : 1, $next],
            'team' => [$reminder->team_id ? 1 : 0, mb_strtolower($reminder->team?->name ?? ''), $next],
            default => [$next],
        };
    }
}
