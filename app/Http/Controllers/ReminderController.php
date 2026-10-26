<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\ReminderRecipient;
use App\Models\Team;
use App\Models\User;
use App\Services\Reminders\ReminderEngine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReminderController extends Controller
{
    public const SNOOZE_OPTIONS = [5, 10, 15, 30, 60, 120];

    public const LEAD_OPTIONS = [30 => '30 minutes', 60 => '1 hour', 120 => '2 hours', 240 => '4 hours', 480 => '8 hours', 1440 => '1 day'];

    public function __construct(private readonly ReminderEngine $engine) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $teams = $user->teams()->orderBy('name')->get();
        $scope = $this->scope($request, $teams);

        $reminders = $this->visible($user, $scope)->get();
        $due = $this->engine->dueNow($user)->filter(fn (ReminderRecipient $r) => $reminders->contains('id', $r->reminder_id));

        return view('reminders.index', [
            'engine' => $this->engine,
            'teams' => $teams,
            'scope' => $scope,
            'due' => $due,
            'upcoming' => $reminders
                ->filter(fn (Reminder $r) => $r->isActive() && ! $due->contains('reminder_id', $r->id))
                ->sortBy(fn (Reminder $r) => $r->recipients->first()?->next_notify_at ?? $r->due_at ?? now()->addYear())
                ->values(),
            'done' => $reminders->filter(fn (Reminder $r) => $r->completed_at)->sortByDesc('completed_at')->take(20)->values(),
        ]);
    }

    /**
     * The live "due now" list, polled by the dashboard and the reminders page.
     */
    public function due(Request $request): JsonResponse
    {
        $user = $request->user();
        $due = $this->engine->dueNow($user);

        return response()->json([
            'ids' => $due->pluck('reminder_id')->all(),
            'html' => view('reminders._due', ['due' => $due, 'engine' => $this->engine])->render(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('reminders.form', $this->formData($request->user(), new Reminder));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request, $user);
        $team = $data['team_id'] ? Team::query()->findOrFail($data['team_id']) : null;

        Gate::authorize('createFor', [Reminder::class, $team]);
        $this->checkAssignee($team, $data['assignee_id']);

        if ($data['type'] === Reminder::DEADLINE && $data['due_at']->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['due_at' => 'The deadline must be in the future.']);
        }

        $reminder = Reminder::query()->create($data + ['created_by' => $user->getKey()]);
        $this->engine->sync($reminder);

        return redirect()->route('reminders.index')->with('status', 'Reminder "'.$reminder->title.'" was added.');
    }

    public function edit(Request $request, Reminder $reminder): View
    {
        Gate::authorize('update', $reminder);

        return view('reminders.form', $this->formData($request->user(), $reminder));
    }

    public function update(Request $request, Reminder $reminder): RedirectResponse
    {
        Gate::authorize('update', $reminder);

        $data = $this->validated($request, $request->user());
        $this->checkAssignee($reminder->team, $data['assignee_id']);

        // The team of a reminder cannot change afterwards.
        unset($data['team_id']);

        $reminder->fill($data);
        $reschedule = $reminder->isDirty(['type', 'due_at', 'lead_minutes', 'interval_minutes', 'assignee_id', 'recurrence', 'weekdays', 'month_days']);
        $reminder->save();

        $reschedule ? $this->engine->reschedule($reminder) : $this->engine->sync($reminder);

        return redirect()->route('reminders.index')->with('status', 'Reminder saved.');
    }

    public function destroy(Reminder $reminder): RedirectResponse
    {
        Gate::authorize('delete', $reminder);

        $reminder->delete();

        return redirect()->route('reminders.index')->with('status', 'Reminder deleted.');
    }

    public function done(Request $request, Reminder $reminder): JsonResponse|RedirectResponse
    {
        $this->engine->done($this->recipient($request, $reminder));

        return $this->acted($request, 'Marked as done.');
    }

    public function snooze(Request $request, Reminder $reminder): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['minutes' => ['required', 'integer', Rule::in(self::SNOOZE_OPTIONS)]]);

        $this->engine->snooze($this->recipient($request, $reminder), (int) $data['minutes']);

        return $this->acted($request, 'Snoozed for '.$data['minutes'].' minutes.');
    }

    public function skip(Request $request, Reminder $reminder): JsonResponse|RedirectResponse
    {
        $this->engine->skipToday($this->recipient($request, $reminder));

        return $this->acted($request, 'Skipped until the next working day.');
    }

    // ---------------------------------------------------------------- helpers

    private function recipient(Request $request, Reminder $reminder): ReminderRecipient
    {
        Gate::authorize('view', $reminder);

        return $reminder->recipients()->where('user_id', $request->user()->getKey())->firstOrFail();
    }

    private function acted(Request $request, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['status' => $message]) : back()->with('status', $message);
    }

    /**
     * @param  Collection<int, Team>  $teams
     */
    private function scope(Request $request, $teams): string
    {
        $scope = (string) $request->query('scope', 'all');

        if ($scope === 'mine' || $scope === 'all') {
            return $scope;
        }

        if (str_starts_with($scope, 'team:') && $teams->contains('id', (int) substr($scope, 5))) {
            return $scope;
        }

        return 'all';
    }

    /**
     * @return Builder<Reminder>
     */
    private function visible(User $user, string $scope)
    {
        $teamIds = $user->teams()->pluck('teams.id');

        return Reminder::query()
            ->whereNull('archived_at')
            ->where(fn ($query) => $query
                ->where(fn ($personal) => $personal->whereNull('team_id')->where('created_by', $user->getKey()))
                ->orWhereIn('team_id', $teamIds))
            ->when($scope === 'mine', fn ($query) => $query->whereNull('team_id'))
            ->when(str_starts_with($scope, 'team:'), fn ($query) => $query->where('team_id', (int) substr($scope, 5)))
            ->with(['team', 'assignee', 'creator', 'recipients' => fn ($query) => $query->where('user_id', $user->getKey())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $user, Reminder $reminder): array
    {
        $teams = $user->teams()->orderBy('name')->get()
            ->filter(fn (Team $team) => Gate::forUser($user)->allows('createFor', [Reminder::class, $team]));

        if ($reminder->exists && $reminder->team) {
            $teams = collect([$reminder->team]);
        }

        return [
            'reminder' => $reminder,
            'user' => $user,
            'teams' => $teams->each(fn (Team $team) => $team->load('users')),
            'leadOptions' => self::LEAD_OPTIONS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, User $user): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in([Reminder::DEADLINE, Reminder::REPEATING, Reminder::SCHEDULED])],
            'recurrence' => ['required_if:type,'.Reminder::SCHEDULED, 'nullable', Rule::in([Reminder::WEEKLY, Reminder::MONTHLY])],
            'weekdays' => ['required_if:recurrence,'.Reminder::WEEKLY, 'nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'month_days' => ['required_if:recurrence,'.Reminder::MONTHLY, 'nullable', 'array'],
            'month_days.*' => ['integer', 'between:-1,31', 'not_in:0'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'completion_mode' => ['nullable', Rule::in([Reminder::ANYONE, Reminder::EACH])],
            'due_at' => ['required_if:type,'.Reminder::DEADLINE, 'nullable', 'date'],
            'lead_minutes' => ['nullable', 'integer', Rule::in(array_keys(self::LEAD_OPTIONS))],
            'nag_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'interval_minutes' => ['required_if:type,'.Reminder::REPEATING, 'nullable', 'integer', 'min:5', 'max:1440'],
            'daily_target' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], [
            'due_at.required_if' => 'A deadline needs a date and time.',
            'interval_minutes.required_if' => 'A repeating reminder needs an interval.',
            'recurrence.required_if' => 'Choose weekly or monthly.',
            'weekdays.required_if' => 'Pick at least one weekday.',
            'month_days.required_if' => 'Pick at least one day of the month.',
        ]);

        $deadline = $data['type'] === Reminder::DEADLINE;
        $repeating = $data['type'] === Reminder::REPEATING;
        $scheduled = $data['type'] === Reminder::SCHEDULED;
        $monthly = $scheduled && $data['recurrence'] === Reminder::MONTHLY;
        $list = fn (?array $values) => collect($values)->map(fn ($value) => (int) $value)->unique()->sort()->implode(',');

        return [
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'type' => $data['type'],
            'team_id' => $data['team_id'] ?? null,
            'assignee_id' => ($data['team_id'] ?? null) ? ($data['assignee_id'] ?? null) : null,
            'completion_mode' => $data['completion_mode'] ?? Reminder::ANYONE,
            'due_at' => $deadline ? CarbonImmutable::parse($data['due_at'], $user->timezone)->utc() : null,
            'lead_minutes' => $data['lead_minutes'] ?? 240,
            'nag_minutes' => $data['nag_minutes'] ?? null,
            'interval_minutes' => $repeating ? (int) $data['interval_minutes'] : null,
            'daily_target' => $repeating ? ($data['daily_target'] ?? null) : null,
            'recurrence' => $scheduled ? $data['recurrence'] : null,
            'weekdays' => $scheduled && ! $monthly ? $list($data['weekdays'] ?? []) : null,
            'month_days' => $monthly ? $list($data['month_days'] ?? []) : null,
            'ignore_work_hours_when_overdue' => $deadline && $request->boolean('ignore_work_hours_when_overdue'),
        ];
    }

    private function checkAssignee(?Team $team, mixed $assigneeId): void
    {
        if ($team && $assigneeId && ! $team->users()->whereKey($assigneeId)->exists()) {
            throw ValidationException::withMessages(['assignee_id' => 'The assignee must be a member of the team.']);
        }
    }
}
