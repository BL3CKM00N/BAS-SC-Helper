@extends('layouts.shell')

@php
    $editing = $reminder->exists;
    $type = old('type', $reminder->type ?? 'deadline');
    $dueValue = old('due_at', $reminder->due_at ? $user->local($reminder->due_at)->format('Y-m-d\TH:i') : '');
    $teamId = old('team_id', $reminder->team_id);
    $inputClass = 'w-full rounded-lg border border-white/15 bg-[#1d1d1a] px-3 py-2.5 text-sm text-white outline-none focus:border-[#1da15e] focus:ring-1 focus:ring-[#1da15e]';
    $teamMembers = $teams->mapWithKeys(fn ($team) => [$team->id => $team->users->map(fn ($member) => ['id' => $member->id, 'name' => $member->name])->values()]);
@endphp

@section('title', ($editing ? 'Edit reminder' : 'New reminder').' - '.config('app.name', 'BAS SC Helper'))

@section('page')
    <a href="{{ route('reminders.index') }}" class="text-sm text-[#b8bab8] hover:text-white">&larr; Reminders</a>
    <h1 class="mt-2 text-2xl font-semibold">{{ $editing ? 'Edit reminder' : 'New reminder' }}</h1>

    <form method="POST" action="{{ $editing ? route('reminders.update', $reminder) : route('reminders.store') }}" class="mt-6 space-y-5 rounded-2xl border border-white/10 bg-[#262623] p-6 [&>*]:max-w-2xl" data-reminder-form>
        @csrf
        @if ($editing) @method('PATCH') @endif

        <div>
            <label for="title" class="mb-1 block text-sm text-[#b8bab8]">What do you need to remember?</label>
            <input id="title" name="title" type="text" required maxlength="255" value="{{ old('title', $reminder->title) }}" class="{{ $inputClass }}">
        </div>

        <div>
            <label for="notes" class="mb-1 block text-sm text-[#b8bab8]">Notes (optional)</label>
            <textarea id="notes" name="notes" rows="2" maxlength="2000" class="{{ $inputClass }}">{{ old('notes', $reminder->notes) }}</textarea>
        </div>

        <fieldset>
            <legend class="mb-2 text-sm text-[#b8bab8]">Kind of reminder</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-[#1d1d1a] p-4 has-[:checked]:border-[#1da15e]">
                    <input type="radio" name="type" value="deadline" @checked($type === 'deadline') class="mt-1 accent-[#1da15e]" data-type-radio>
                    <span><span class="block text-sm font-medium">Deadline</span><span class="text-xs text-[#b8bab8]">Reminds you more often as the deadline gets closer, until it is done.</span></span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-[#1d1d1a] p-4 has-[:checked]:border-[#1da15e]">
                    <input type="radio" name="type" value="repeating" @checked($type === 'repeating') class="mt-1 accent-[#1da15e]" data-type-radio>
                    <span><span class="block text-sm font-medium">Repeats during the day</span><span class="text-xs text-[#b8bab8]">Comes back every X minutes after you did it, for things you do several times a day.</span></span>
                </label>
            </div>
        </fieldset>

        <div class="space-y-4" data-type-panel="deadline">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="due_at" class="mb-1 block text-sm text-[#b8bab8]">Deadline ({{ $user->timezone }})</label>
                    <input id="due_at" name="due_at" type="datetime-local" value="{{ $dueValue }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label for="lead_minutes" class="mb-1 block text-sm text-[#b8bab8]">Start reminding</label>
                    <select id="lead_minutes" name="lead_minutes" class="{{ $inputClass }}">
                        @foreach ($leadOptions as $minutes => $label)
                            <option value="{{ $minutes }}" @selected((int) old('lead_minutes', $reminder->lead_minutes) === $minutes)>{{ $label }} before</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="ignore_work_hours_when_overdue" value="1" @checked(old('ignore_work_hours_when_overdue', $reminder->ignore_work_hours_when_overdue)) class="mt-0.5 size-4 accent-[#1da15e]">
                <span>Keep reminding me when it is overdue, even outside my work hours</span>
            </label>
        </div>

        <div class="space-y-4" data-type-panel="repeating">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="interval_minutes" class="mb-1 block text-sm text-[#b8bab8]">Come back every (minutes)</label>
                    <input id="interval_minutes" name="interval_minutes" type="number" min="5" max="1440" value="{{ old('interval_minutes', $reminder->interval_minutes ?? 60) }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label for="daily_target" class="mb-1 block text-sm text-[#b8bab8]">Times per day (optional)</label>
                    <input id="daily_target" name="daily_target" type="number" min="1" max="50" value="{{ old('daily_target', $reminder->daily_target) }}" class="{{ $inputClass }}">
                </div>
            </div>
        </div>

        <div>
            <label for="nag_minutes" class="mb-1 block text-sm text-[#b8bab8]">If I ignore it, remind me again every (minutes)</label>
            <input id="nag_minutes" name="nag_minutes" type="number" min="1" max="1440" placeholder="Automatic" value="{{ old('nag_minutes', $reminder->nag_minutes) }}" class="{{ $inputClass }}">
            <p class="mt-1 text-xs text-[#b8bab8]">Leave empty for automatic: a deadline gets faster reminders as it gets closer (every hour, then 30, 10 and 5 minutes), a repeating reminder nags every 10 minutes.</p>
        </div>

        @if ($teams->isNotEmpty())
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="team_id" class="mb-1 block text-sm text-[#b8bab8]">For</label>
                    <select id="team_id" name="team_id" class="{{ $inputClass }}" data-team-select @disabled($editing)>
                        <option value="">Just me</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}" @selected((int) $teamId === $team->id)>Team: {{ $team->name }}</option>
                        @endforeach
                    </select>
                    @if ($editing)
                        <input type="hidden" name="team_id" value="{{ $reminder->team_id }}">
                    @endif
                </div>
                <div data-team-panel>
                    <label for="assignee_id" class="mb-1 block text-sm text-[#b8bab8]">Who</label>
                    <select id="assignee_id" name="assignee_id" class="{{ $inputClass }}" data-assignee-select data-selected="{{ old('assignee_id', $reminder->assignee_id) }}"></select>
                </div>
            </div>

            <fieldset data-team-panel data-everyone-panel>
                <legend class="mb-2 text-sm text-[#b8bab8]">When it goes to the whole team</legend>
                <label class="mb-2 flex items-start gap-3 text-sm">
                    <input type="radio" name="completion_mode" value="anyone" @checked(old('completion_mode', $reminder->completion_mode) !== 'each') class="mt-0.5 accent-[#1da15e]">
                    <span>Anyone can finish it<span class="block text-xs text-[#b8bab8]">The first person to mark it done finishes it for everyone (a repeating reminder then resets for everyone).</span></span>
                </label>
                <label class="flex items-start gap-3 text-sm">
                    <input type="radio" name="completion_mode" value="each" @checked(old('completion_mode', $reminder->completion_mode) === 'each') class="mt-0.5 accent-[#1da15e]">
                    <span>Everyone does it themselves<span class="block text-xs text-[#b8bab8]">Each member has to mark it done for themselves.</span></span>
                </label>
            </fieldset>
        @endif

        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-[#1da15e] px-5 py-2.5 text-sm font-medium transition hover:bg-[#178a4f]">{{ $editing ? 'Save' : 'Add reminder' }}</button>
            <a href="{{ route('reminders.index') }}" class="rounded-lg border border-white/15 px-5 py-2.5 text-sm transition hover:bg-white/5">Cancel</a>
        </div>
    </form>

    <script>
        (function () {
            var form = document.querySelector('[data-reminder-form]');
            var members = @json($teamMembers);
            var me = {{ $user->id }};

            function type() {
                var checked = form.querySelector('[data-type-radio]:checked');
                return checked ? checked.value : 'deadline';
            }

            function update() {
                form.querySelectorAll('[data-type-panel]').forEach(function (panel) {
                    panel.hidden = panel.getAttribute('data-type-panel') !== type();
                });

                var teamSelect = form.querySelector('[data-team-select]');
                var teamId = teamSelect ? (teamSelect.value || (form.querySelector('input[name="team_id"][type="hidden"]') || {}).value) : '';
                var assignee = form.querySelector('[data-assignee-select]');

                form.querySelectorAll('[data-team-panel]').forEach(function (panel) { panel.hidden = !teamId; });

                if (assignee && teamId) {
                    var selected = assignee.getAttribute('data-selected');
                    assignee.innerHTML = '<option value="">Everyone in the team</option>' + (members[teamId] || []).map(function (member) {
                        return '<option value="' + member.id + '"' + (String(member.id) === String(selected) ? ' selected' : '') + '>' + (member.id === me ? 'Me' : member.name) + '</option>';
                    }).join('');
                    assignee.onchange = function () { assignee.setAttribute('data-selected', assignee.value); update(); };
                }

                var everyone = form.querySelector('[data-everyone-panel]');
                if (everyone) { everyone.hidden = !teamId || (assignee && assignee.value !== ''); }
            }

            form.addEventListener('change', update);
            update();
        })();
    </script>
@endsection
