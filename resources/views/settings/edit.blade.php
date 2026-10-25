@extends('layouts.shell')

@section('title', 'Settings - '.config('app.name', 'BAS SC Helper'))

@php
    $inputClass = 'w-full rounded-lg border border-white/15 bg-[#1d1d1a] px-3 py-2.5 text-sm text-white outline-none focus:border-[#1da15e] focus:ring-1 focus:ring-[#1da15e]';
    $workDays = array_map('intval', explode(',', (string) $user->work_days));
@endphp

@section('page')
    <h1 class="text-2xl font-semibold">Settings</h1>

    <section class="mt-8 rounded-2xl border border-white/10 bg-[#262623] p-6" data-push-panel>
        <h2 class="text-sm font-medium text-[#b8bab8]">Notifications</h2>
        <p class="mt-1 max-w-2xl text-sm text-[#b8bab8]">Get your reminders as a notification on this device, also when the app is closed. Turn it on for each phone or computer you use.</p>
        <p class="mt-4 text-sm" data-push-status role="status">Checking this device...</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" data-push-enable hidden class="rounded-lg bg-[#1da15e] px-4 py-2.5 text-sm font-medium transition hover:bg-[#178a4f]">Turn on notifications</button>
            <button type="button" data-push-test hidden class="rounded-lg border border-white/15 px-4 py-2.5 text-sm transition hover:bg-white/5">Send a test</button>
            <button type="button" data-push-disable hidden class="rounded-lg border border-white/15 px-4 py-2.5 text-sm text-red-300 transition hover:bg-red-500/10">Turn off</button>
        </div>
    </section>

    <section class="mt-6 rounded-2xl border border-white/10 bg-[#262623] p-6">
        <h2 class="text-sm font-medium text-[#b8bab8]">Work hours</h2>
        <p class="mt-1 max-w-2xl text-sm text-[#b8bab8]">Reminders are only sent during your work hours. Anything that comes due outside them waits until your next working period.</p>

        <form method="POST" action="{{ route('settings.update') }}" class="mt-6 max-w-2xl space-y-5">
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

    <section class="mt-6 rounded-2xl border border-white/10 bg-[#262623] p-6" id="password">
        <h2 class="text-sm font-medium text-[#b8bab8]">Password</h2>
        <p class="mt-1 max-w-2xl text-sm text-[#b8bab8]">Choose a new password. When you change it, every other device you are signed in on is signed out.</p>

        <form method="POST" action="{{ route('settings.password') }}" class="mt-6 max-w-2xl space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="mb-1 block text-sm text-[#b8bab8]">Current password</label>
                <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="{{ $inputClass }}">
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="new_password" class="mb-1 block text-sm text-[#b8bab8]">New password</label>
                    <input id="new_password" name="password" type="password" required minlength="10" autocomplete="new-password" class="{{ $inputClass }}">
                    <p class="mt-1 text-xs text-[#b8bab8]">At least 10 characters.</p>
                </div>
                <div>
                    <label for="new_password_confirmation" class="mb-1 block text-sm text-[#b8bab8]">Repeat the new password</label>
                    <input id="new_password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="{{ $inputClass }}">
                </div>
            </div>

            @if ($errors->password->any())
                <div role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-300">
                    @foreach ($errors->password->all() as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            @endif

            <button type="submit" class="rounded-lg bg-[#1da15e] px-5 py-2.5 text-sm font-medium transition hover:bg-[#178a4f]">Change password</button>
        </form>
    </section>

    <section class="mt-6 rounded-2xl border border-white/10 bg-[#262623] p-6" id="devices" aria-labelledby="devices-heading">
        <h2 id="devices-heading" class="text-sm font-medium text-[#b8bab8]">Devices you are signed in on</h2>
        <p class="mt-1 max-w-2xl text-sm text-[#b8bab8]">Every browser or installed app where you are signed in. If you do not recognise one, sign it out and change your password.</p>

        <ul class="mt-5 divide-y divide-white/10 rounded-xl border border-white/10 bg-[#1d1d1a]">
            @foreach ($devices as $device)
                @php
                    $active = $device['last_active'];
                    $recent = $active->greaterThan(now()->subMinutes(5));
                @endphp
                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium">
                            {{ $device['label'] }}
                            @if ($device['current'])
                                <span class="ml-2 rounded-full bg-[#1da15e]/15 px-2.5 py-0.5 text-xs font-normal text-[#7be0a8]">This device</span>
                            @endif
                        </p>
                        <p class="mt-0.5 text-xs text-[#b8bab8]">
                            {{ ucfirst($device['device']) }}
                            @if ($device['ip']) &middot; {{ $device['ip'] }} @endif
                            &middot; <span title="{{ $user->local($active)->format('D j M Y H:i') }}">{{ $recent ? 'Active now' : 'Last active '.$active->diffForHumans() }}</span>
                        </p>
                    </div>

                    @unless ($device['current'])
                        <form method="POST" action="{{ route('settings.devices.destroy', $device['handle']) }}"
                            data-confirm="{{ $device['label'] }} will be signed out." data-confirm-title="Sign out this device?" data-confirm-button="Sign out">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg border border-white/15 px-3 py-1.5 text-sm transition hover:bg-white/5">Sign out</button>
                        </form>
                    @endunless
                </li>
            @endforeach
        </ul>

        <div class="mt-5 flex flex-wrap gap-2">
            @if ($devices->where('current', false)->isNotEmpty())
                <form method="POST" action="{{ route('settings.devices.destroy-others') }}"
                    data-confirm="Every device except this one will be signed out." data-confirm-title="Sign out your other devices?" data-confirm-button="Sign out other devices">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg border border-white/15 px-4 py-2.5 text-sm transition hover:bg-white/5">Sign out other devices</button>
                </form>
            @endif

            <form method="POST" action="{{ route('settings.devices.sign-out-everywhere') }}"
                data-confirm="You will be signed out on all your devices, including this one." data-confirm-title="Sign out everywhere?" data-confirm-button="Sign out everywhere" data-confirm-tone="danger">
                @csrf
                <button type="submit" class="rounded-lg border border-red-500/30 px-4 py-2.5 text-sm text-red-300 transition hover:bg-red-500/10">Sign out everywhere</button>
            </form>
        </div>
    </section>
@endsection
