<?php

namespace App\Services\Admin;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Maintenance mode, kept in the database instead of `artisan down` so that admins can still sign in and the
 * scheduler keeps running: reminders and push notifications keep going out while everybody else is locked out.
 */
class Maintenance
{
    public const DEFAULT_MESSAGE = 'BAS SC Helper is being worked on and will be back soon.';

    public function __construct(private readonly AppSettings $settings) {}

    public function isOn(): bool
    {
        return (bool) $this->settings->get('maintenance.on', false);
    }

    public function message(): string
    {
        return (string) ($this->settings->get('maintenance.message') ?: self::DEFAULT_MESSAGE);
    }

    public function backAt(): ?CarbonImmutable
    {
        $value = $this->settings->get('maintenance.back_at');

        return $value ? CarbonImmutable::parse($value) : null;
    }

    public function enable(?string $message, ?CarbonInterface $backAt, ?User $actor = null): void
    {
        $this->settings->setMany([
            'maintenance.on' => true,
            'maintenance.message' => $message ?: null,
            'maintenance.back_at' => $backAt?->toIso8601String(),
        ]);

        Audit::record('maintenance.enabled', 'Turned maintenance mode on', null, ['message' => $message, 'back_at' => $backAt?->toIso8601String()], $actor);
    }

    public function disable(?User $actor = null): void
    {
        $this->settings->set('maintenance.on', false);

        Audit::record('maintenance.disabled', 'Turned maintenance mode off', null, [], $actor);
    }

    /**
     * The message people see, with the expected time in their own time zone.
     */
    public function textFor(?User $user): string
    {
        $text = $this->message();

        if ($back = $this->backAt()) {
            $local = $back->setTimezone($user?->timezone ?: config('app.timezone'));
            $text .= ' Expected back '.($local->isToday() ? 'at '.$local->format('H:i') : 'on '.$local->format('d M \a\t H:i')).'.';
        }

        return $text;
    }
}
