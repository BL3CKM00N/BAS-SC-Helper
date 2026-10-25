@extends('account.layout')

@section('account-title', 'Security')

@php
    $inputClass = 'w-full rounded-lg border border-white/15 bg-[#1d1d1a] px-3 py-2.5 text-sm text-white outline-none focus:border-[#1da15e] focus:ring-1 focus:ring-[#1da15e]';
@endphp

@section('account')
    <section class="rounded-2xl border border-white/10 bg-[#262623] p-6" id="password">
        <h2 class="text-sm font-medium text-[#b8bab8]">Password</h2>
        <p class="mt-1 max-w-2xl text-sm text-[#b8bab8]">Choose a new password. When you change it, every other device you are signed in on is signed out.</p>

        <form method="POST" action="{{ route('account.security.password') }}" class="mt-6 max-w-2xl space-y-4">
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

    <section class="rounded-2xl border border-white/10 bg-[#262623] p-6" id="devices" aria-labelledby="devices-heading">
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
                        <form method="POST" action="{{ route('account.security.devices.destroy', $device['handle']) }}"
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
                <form method="POST" action="{{ route('account.security.devices.destroy-others') }}"
                    data-confirm="Every device except this one will be signed out." data-confirm-title="Sign out your other devices?" data-confirm-button="Sign out other devices">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg border border-white/15 px-4 py-2.5 text-sm transition hover:bg-white/5">Sign out other devices</button>
                </form>
            @endif

            <form method="POST" action="{{ route('account.security.devices.sign-out-everywhere') }}"
                data-confirm="You will be signed out on all your devices, including this one." data-confirm-title="Sign out everywhere?" data-confirm-button="Sign out everywhere" data-confirm-tone="danger">
                @csrf
                <button type="submit" class="rounded-lg border border-red-500/30 px-4 py-2.5 text-sm text-red-300 transition hover:bg-red-500/10">Sign out everywhere</button>
            </form>
        </div>
    </section>
@endsection
