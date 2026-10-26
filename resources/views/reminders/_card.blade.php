@php
    $user = auth()->user();
    $mine = $reminder->recipients->first();
    $next = $mine?->next_notify_at;
@endphp

<article class="rounded-xl border border-white/10 bg-[#262623] p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="font-medium {{ $reminder->completed_at ? 'text-[#b8bab8] line-through' : '' }}">{{ $reminder->title }}</h3>
            <p class="mt-0.5 text-xs text-[#b8bab8]">
                <span class="rounded-full bg-white/10 px-2 py-0.5">{{ $reminder->team?->name ?? 'Personal' }}</span>
                @if ($reminder->assignee)
                    &middot; for {{ $reminder->assignee->id === $user->id ? 'you' : $reminder->assignee->name }}
                @elseif ($reminder->team)
                    &middot; {{ $reminder->completion_mode === 'each' ? 'everyone does it' : 'anyone can finish it' }}
                @endif
                &middot;
                @if ($reminder->isDeadline())
                    {{ $reminder->completed_at ? 'Done '.$user->local($reminder->completed_at)->format('D j M H:i') : 'Due '.$user->local($reminder->due_at)->format('D j M H:i') }}
                @elseif ($reminder->isScheduled())
                    {{ $reminder->scheduleLabel() }}
                @else
                    Every {{ $reminder->interval_minutes }} min
                    @if ($reminder->daily_target) &middot; {{ $engine->doneToday($reminder, $user) }} of {{ $reminder->daily_target }} today @endif
                @endif
                @if (! $reminder->completed_at && $next)
                    &middot; next reminder {{ $user->local($next)->isToday() ? 'at '.$user->local($next)->format('H:i') : $user->local($next)->format('D j M H:i') }}
                @endif
            </p>
            @if ($reminder->notes)
                <p class="mt-2 text-sm text-[#b8bab8]">{{ $reminder->notes }}</p>
            @endif
        </div>

        <div class="flex items-center gap-2">
            @if ($mine && ! $reminder->completed_at && ($reminder->isRepeating() || ($reminder->isScheduled() && $mine->pending_since)))
                <form method="POST" action="{{ route('reminders.done', $reminder) }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-white/15 px-3 py-1.5 text-xs transition hover:bg-white/5">Done now</button>
                </form>
            @endif
            @can('update', $reminder)
                <a href="{{ route('reminders.edit', $reminder) }}" class="rounded-lg border border-white/15 px-3 py-1.5 text-xs transition hover:bg-white/5">Edit</a>
            @endcan
            @can('delete', $reminder)
                <form method="POST" action="{{ route('reminders.destroy', $reminder) }}"
                    data-confirm="&quot;{{ $reminder->title }}&quot; will be deleted for good{{ $reminder->team ? ' for the whole team' : '' }}."
                    data-confirm-title="Delete this reminder?" data-confirm-button="Delete" data-confirm-tone="danger">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg border border-white/15 px-3 py-1.5 text-xs text-red-300 transition hover:bg-red-500/10">Delete</button>
                </form>
            @endcan
        </div>
    </div>
</article>
