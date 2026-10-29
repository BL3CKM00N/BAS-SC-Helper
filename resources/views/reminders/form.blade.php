@extends('layouts.shell')

@php
    $editing = $reminder->exists;
    $type = old('type', $reminder->type ?? 'deadline');
    $recurrence = (string) old('recurrence', $reminder->recurrence);
    $frequency = old('frequency', $reminder->exists && $reminder->interval_minutes === null ? 'daily' : ($reminder->exists ? 'interval' : 'daily'));
    $pickedWeekdays = array_map('intval', (array) old('weekdays', $reminder->weekdayList()));
    $pickedMonthDays = array_map('intval', (array) old('month_days', $reminder->monthDayList()));
    $dayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
    $chip = 'flex cursor-pointer items-center justify-center rounded-lg border border-white/15 bg-[#1d1d1a] px-3 py-2 text-sm transition has-[:checked]:border-[#1da15e] has-[:checked]:bg-[#1da15e]/20';
    $startsAt = \Carbon\CarbonImmutable::createFromFormat('H:i', $user->work_start ?: '08:00')->addMinutes(\App\Services\Reminders\RecurrenceSchedule::START_DELAY_MINUTES)->format('H:i');
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

        @if ($folders->isNotEmpty())
            <div>
                <label for="folder_id" class="mb-1 block text-sm text-[#b8bab8]">Folder (optional)</label>
                <select id="folder_id" name="folder_id" class="{{ $inputClass }}">
                    <option value="">No folder</option>
                    @foreach ($folders as $option)
                        <option value="{{ $option->id }}" @selected((int) old('folder_id', $folderId) === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <fieldset>
            <legend class="mb-2 text-sm text-[#b8bab8]">Kind of reminder</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-[#1d1d1a] p-4 has-[:checked]:border-[#1da15e]">
                    <input type="radio" name="type" value="deadline" @checked($type === 'deadline') class="mt-1 accent-[#1da15e]" data-type-radio>
                    <span><span class="block text-sm font-medium">Deadline</span><span class="text-xs text-[#b8bab8]">Reminds you more often as the deadline gets closer, until it is done.</span></span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-[#1d1d1a] p-4 has-[:checked]:border-[#1da15e]">
                    <input type="radio" name="type" value="repeating" @checked($type === 'repeating') class="mt-1 accent-[#1da15e]" data-type-radio>
                    <span><span class="block text-sm font-medium">Recurring</span><span class="text-xs text-[#b8bab8]">Comes back on the days you choose, once a day or several times a day.</span></span>
                </label>
            </div>
        </fieldset>

        <div class="space-y-4" data-type-panel="deadline">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="min-w-0">
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

        <div class="space-y-5" data-type-panel="repeating">
            <div>
                <p class="mb-2 text-sm text-[#b8bab8]">Which days</p>
                <div class="grid gap-2 sm:grid-cols-3">
                    <label class="{{ $chip }}"><input type="radio" name="recurrence" value="" @checked($recurrence === '') class="sr-only" data-recurrence>Every work day</label>
                    <label class="{{ $chip }}"><input type="radio" name="recurrence" value="weekly" @checked($recurrence === 'weekly') class="sr-only" data-recurrence>Chosen weekdays</label>
                    <label class="{{ $chip }}"><input type="radio" name="recurrence" value="monthly" @checked($recurrence === 'monthly') class="sr-only" data-recurrence>Days of the month</label>
                </div>
            </div>

            <div data-recurrence-panel="weekly">
                <p class="mb-2 text-sm text-[#b8bab8]">On these days</p>
                <div class="grid grid-cols-7 gap-2">
                    @foreach ($dayNames as $number => $name)
                        <label class="{{ $chip }}"><input type="checkbox" name="weekdays[]" value="{{ $number }}" @checked(in_array($number, $pickedWeekdays, true)) class="sr-only" data-weekday>{{ $name }}</label>
                    @endforeach
                </div>
                <div class="mt-2 flex gap-3 text-xs text-[#b8bab8]">
                    <button type="button" class="underline" data-weekday-preset="1,2,3,4,5">Mon to Fri</button>
                    <button type="button" class="underline" data-weekday-preset="1,2,3,4,5,6,7">Every day</button>
                </div>
            </div>

            <div data-recurrence-panel="monthly">
                <p class="mb-2 text-sm text-[#b8bab8]">On these days of the month</p>
                <div class="grid grid-cols-7 gap-2">
                    @foreach (range(1, 31) as $day)
                        <label class="{{ $chip }} px-0"><input type="checkbox" name="month_days[]" value="{{ $day }}" @checked(in_array($day, $pickedMonthDays, true)) class="sr-only" data-monthday>{{ $day }}</label>
                    @endforeach
                    <label class="{{ $chip }} col-span-3 px-0"><input type="checkbox" name="month_days[]" value="-1" @checked(in_array(-1, $pickedMonthDays, true)) class="sr-only" data-monthday>Last day</label>
                </div>
                <p class="mt-2 text-xs text-[#b8bab8]">A day that does not exist in a month (the 31st in April) falls on the last day of that month.</p>
            </div>

            <p class="hidden rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-200" data-offday-notice></p>
            <p class="text-xs text-[#b8bab8]" data-schedule-preview></p>

            <div>
                <p class="mb-2 text-sm text-[#b8bab8]">How often on those days</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="{{ $chip }}"><input type="radio" name="frequency" value="daily" @checked($frequency === 'daily') class="sr-only" data-frequency>Once a day</label>
                    <label class="{{ $chip }}"><input type="radio" name="frequency" value="interval" @checked($frequency === 'interval') class="sr-only" data-frequency>Several times a day</label>
                </div>
                <div class="mt-3 grid gap-4 sm:grid-cols-2" data-frequency-panel="daily">
                    <div>
                        <label for="due_time" class="mb-1 block text-sm text-[#b8bab8]">Due by (optional)</label>
                        <input id="due_time" name="due_time" type="time" value="{{ old('due_time', $reminder->due_time) }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label for="recurring_lead_minutes" class="mb-1 block text-sm text-[#b8bab8]">Start reminding</label>
                        <select id="recurring_lead_minutes" name="recurring_lead_minutes" class="{{ $inputClass }}">
                            @foreach ($leadOptions as $minutes => $label)
                                <option value="{{ $minutes }}" @selected((int) old('recurring_lead_minutes', $reminder->lead_minutes) === $minutes)>{{ $label }} before</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="mt-2 text-xs text-[#b8bab8]" data-frequency-panel="daily">Without a due time the first reminder comes {{ \App\Services\Reminders\RecurrenceSchedule::START_DELAY_MINUTES }} minutes after your work day starts ({{ $startsAt }}). With one ({{ $user->timezone }} time), it starts that long before the due time, goes faster the closer it gets (every hour, then 30, 10 and 5 minutes) and keeps nagging every 5 minutes after it until your work day ends. Either way it nags until you mark it done. A day you choose always counts, also when it is not one of your work days.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2" data-frequency-panel="interval">
                <div>
                    <label for="interval_minutes" class="mb-1 block text-sm text-[#b8bab8]">Come back every (minutes)</label>
                    <input id="interval_minutes" name="interval_minutes" type="number" min="5" max="1440" value="{{ old('interval_minutes', $reminder->interval_minutes ?? 60) }}" class="{{ $inputClass }}">
                    <p class="mt-1 text-xs text-[#b8bab8]">After you mark it done. The timer rolls on from then.</p>
                </div>
                <div>
                    <label for="daily_target" class="mb-1 block text-sm text-[#b8bab8]">Times per day (optional)</label>
                    <input id="daily_target" name="daily_target" type="number" min="1" max="50" value="{{ old('daily_target', $reminder->daily_target) }}" class="{{ $inputClass }}">
                    <p class="mt-1 text-xs text-[#b8bab8]">When reached, it waits for the next day.</p>
                </div>
            </div>
        </div>

        <div>
            <label for="nag_minutes" class="mb-1 block text-sm text-[#b8bab8]">If I ignore it, remind me again every (minutes)</label>
            <input id="nag_minutes" name="nag_minutes" type="number" min="1" max="1440" placeholder="Automatic" value="{{ old('nag_minutes', $reminder->nag_minutes) }}" class="{{ $inputClass }}">
            <p class="mt-1 text-xs text-[#b8bab8]">Leave empty for automatic: a deadline gets faster reminders as it gets closer (every hour, then 30, 10 and 5 minutes), a recurring reminder nags every 10 minutes.</p>
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

            var workDays = {{ Js::from(array_map('intval', array_filter(explode(',', (string) $user->work_days)))) }};
            var dayNames = {1: 'Monday', 2: 'Tuesday', 3: 'Wednesday', 4: 'Thursday', 5: 'Friday', 6: 'Saturday', 7: 'Sunday'};

            function ticked(selector) {
                return Array.prototype.map.call(form.querySelectorAll(selector + ':checked'), function (box) { return parseInt(box.value, 10); });
            }

            function matches(date, weekly, days) {
                if (weekly) { return days.indexOf(date.getDay() === 0 ? 7 : date.getDay()) !== -1; }
                var last = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
                return days.some(function (day) { return date.getDate() === (day === -1 ? last : Math.min(day, last)); });
            }

            function scheduleHints() {
                var mode = (form.querySelector('[data-recurrence]:checked') || {}).value || '';
                var weekly = mode !== 'monthly';
                var days = mode === 'weekly' ? ticked('[data-weekday]') : (mode === 'monthly' ? ticked('[data-monthday]') : workDays);

                form.querySelectorAll('[data-recurrence-panel]').forEach(function (panel) {
                    panel.hidden = panel.getAttribute('data-recurrence-panel') !== mode;
                });

                var frequency = (form.querySelector('[data-frequency]:checked') || {}).value || 'daily';
                form.querySelectorAll('[data-frequency-panel]').forEach(function (panel) {
                    panel.hidden = panel.getAttribute('data-frequency-panel') !== frequency;
                });

                var notice = form.querySelector('[data-offday-notice]');
                var off = mode === 'weekly' ? days.filter(function (day) { return workDays.indexOf(day) === -1; }) : [];
                notice.textContent = off.length ? off.map(function (day) { return dayNames[day]; }).join(' and ') + (off.length > 1 ? ' are not work days' : ' is not a work day') + ' for you. The reminder will still come on ' + (off.length > 1 ? 'those days' : 'that day') + '.' : '';
                notice.classList.toggle('hidden', !off.length);

                var preview = form.querySelector('[data-schedule-preview]');
                var next = [];
                for (var i = 0; i < 400 && next.length < 3 && days.length; i++) {
                    var date = new Date();
                    date.setDate(date.getDate() + i);
                    if (matches(date, weekly, days)) { next.push(date.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' })); }
                }
                preview.textContent = next.length ? 'Next: ' + next.join(', ') : '';
            }

            form.querySelectorAll('[data-weekday-preset]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var wanted = button.getAttribute('data-weekday-preset').split(',').map(Number);
                    form.querySelectorAll('[data-weekday]').forEach(function (box) { box.checked = wanted.indexOf(parseInt(box.value, 10)) !== -1; });
                    scheduleHints();
                });
            });

            form.addEventListener('change', function () { update(); scheduleHints(); });
            update();
            scheduleHints();
        })();
    </script>
@endsection
