@extends('account.layout')

@section('account-title', 'Work hours')

@php
    $inputClass = 'w-full rounded-lg border border-white/15 bg-[#1d1d1a] px-3 py-2.5 text-sm text-white outline-none focus:border-[#1da15e] focus:ring-1 focus:ring-[#1da15e]';
@endphp
@php
    $workDays = array_map('intval', explode(',', (string) $user->work_days));
@endphp

@section('account')
    <section class="rounded-2xl border border-white/10 bg-[#262623] p-6">
        <h2 class="text-sm font-medium text-[#b8bab8]">Work hours</h2>
        <p class="mt-1 max-w-2xl text-sm text-[#b8bab8]">Reminders are only sent during your work hours. Anything that comes due outside them waits until your next working period.</p>

        <form method="POST" action="{{ route('account.work-hours.update') }}" class="mt-6 max-w-2xl space-y-5">
            @csrf
            @method('PATCH')

            <fieldset>
                <legend class="mb-2 text-sm text-[#b8bab8]">Working days</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach ($days as $number => $label)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-white/10 px-3 py-1.5 text-sm has-[:checked]:border-[#1da15e] has-[:checked]:bg-[#1da15e]/10">
                            <input type="checkbox" name="work_days[]" value="{{ $number }}" @checked(in_array($number, old('work_days', $workDays), true)) class="size-4 accent-[#1da15e]">
                            {{ substr($label, 0, 3) }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="work_start" class="mb-1 block text-sm text-[#b8bab8]">Start of the day</label>
                    <input id="work_start" name="work_start" type="time" required value="{{ old('work_start', $user->work_start) }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label for="work_end" class="mb-1 block text-sm text-[#b8bab8]">End of the day</label>
                    <input id="work_end" name="work_end" type="time" required value="{{ old('work_end', $user->work_end) }}" class="{{ $inputClass }}">
                </div>
            </div>

            <div>
                <label for="timezone" class="mb-1 block text-sm text-[#b8bab8]">Time zone</label>
                <input id="timezone" name="timezone" type="text" list="timezones" required value="{{ old('timezone', $user->timezone) }}" class="{{ $inputClass }}">
                <datalist id="timezones">
                    @foreach ($timezones as $zone)
                        <option value="{{ $zone }}"></option>
                    @endforeach
                </datalist>
            </div>

            <button type="submit" class="rounded-lg bg-[#1da15e] px-5 py-2.5 text-sm font-medium transition hover:bg-[#178a4f]">Save</button>
        </form>
    </section>
@endsection
