@php
    $user = auth()->user();
@endphp

@forelse ($due as $recipient)
    @php
        $reminder = $recipient->reminder;
        $dueMoment = $reminder->followsDays() ? \App\Services\Reminders\RecurrenceSchedule::dueMoment($reminder, $user, $recipient->pending_since ?? now()) : null;
        $overdue = ($reminder->isDeadline() && $reminder->due_at && $reminder->due_at->isPast()) || ($dueMoment && $dueMoment->isPast());
        $since = $user->local($recipient->pending_since ?? $recipient->next_notify_at);
    @endphp

    <article class="rounded-xl border {{ $overdue ? 'border-red-500/40' : 'border-white/10' }} bg-[#1d1d1a] p-4" data-reminder-id="{{ $reminder->id }}">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <h3 class="font-medium">{{ $reminder->title }}</h3>
                <p class="mt-0.5 text-xs text-[#b8bab8]">
                    @if ($reminder->team) {{ $reminder->team->name }} &middot; @endif
                    @if ($reminder->isDeadline())
                        <span class="{{ $overdue ? 'text-red-300' : '' }}">{{ $overdue ? 'Overdue since' : 'Due' }} {{ $user->local($reminder->due_at)->format('D j M H:i') }}</span>
                    @elseif ($reminder->followsDays())
                        <span class="{{ $overdue ? 'text-red-300' : '' }}">{{ $reminder->scheduleLabel() }}{{ $overdue ? ' (overdue)' : '' }}</span>
                        @if ($reminder->daily_target) &middot; done {{ $engine->doneToday($reminder, $user) }} of {{ $reminder->daily_target }} today @endif
                    @else
                        Repeats every {{ $reminder->interval_minutes }} min
                        @if ($reminder->daily_target)
                            &middot; done {{ $engine->doneToday($reminder, $user) }} of {{ $reminder->daily_target }} today
                        @else
                            &middot; done {{ $engine->doneToday($reminder, $user) }} today
                        @endif
                    @endif
                    &middot; waiting since {{ $since->format('H:i') }}
                </p>
                @if ($reminder->notes)
                    <p class="mt-2 text-sm text-[#b8bab8]">{{ $reminder->notes }}</p>
                @endif
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('reminders.done', $reminder) }}">
                @csrf
                <button type="submit" class="rounded-lg bg-[#1da15e] px-4 py-1.5 text-sm font-medium transition hover:bg-[#178a4f]">Done</button>
            </form>

            <details class="group relative" data-snooze>
                <summary class="cursor-pointer list-none rounded-lg border border-white/15 px-2.5 py-1.5 text-xs text-[#b8bab8] transition marker:hidden hover:bg-white/5 group-open:bg-white/10 [&::-webkit-details-marker]:hidden">Snooze</summary>
                <form method="POST" action="{{ route('reminders.snooze', $reminder) }}" class="absolute left-0 top-full z-10 mt-1 flex w-max items-center gap-1 rounded-lg border border-white/15 bg-[#262623] p-1.5 shadow-lg">
                    @csrf
                    @foreach ([10, 30, 60] as $minutes)
                        <button type="submit" name="minutes" value="{{ $minutes }}" class="rounded-lg border border-white/15 px-2.5 py-1.5 text-xs transition hover:bg-white/5">{{ $minutes === 60 ? '1 h' : $minutes.' min' }}</button>
                    @endforeach
                </form>
            </details>

            <form method="POST" action="{{ route('reminders.skip', $reminder) }}"
                data-confirm="&quot;{{ $reminder->title }}&quot; will be hidden until your next working day."
                data-confirm-title="Skip for today?" data-confirm-button="Skip today">
                @csrf
                <button type="submit" class="rounded-lg border border-white/15 px-2.5 py-1.5 text-xs text-[#b8bab8] transition hover:bg-white/5">Skip today</button>
            </form>

            @can('update', $reminder)
                <a href="{{ route('reminders.edit', $reminder) }}" class="rounded-lg border border-white/15 px-2.5 py-1.5 text-xs text-[#b8bab8] transition hover:bg-white/5">Edit</a>
            @endcan
        </div>
    </article>
@empty
    <p class="rounded-xl border border-dashed border-white/15 px-4 py-6 text-center text-sm text-[#b8bab8]">Nothing needs your attention right now.</p>
@endforelse
