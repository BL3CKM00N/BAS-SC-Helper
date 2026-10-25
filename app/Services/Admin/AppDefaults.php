<?php

namespace App\Services\Admin;

/**
 * The defaults new accounts and new teams start with, as set by an admin. Existing accounts and teams are
 * never changed by them.
 */
class AppDefaults
{
    public const BUILT_IN = [
        'timezone' => 'Europe/Amsterdam',
        'work_days' => '1,2,3,4,5',
        'work_start' => '08:00',
        'work_end' => '17:30',
        'members_can_create' => false,
    ];

    public function __construct(private readonly AppSettings $settings) {}

    /**
     * @return array{timezone: string, work_days: string, work_start: string, work_end: string, members_can_create: bool}
     */
    public function all(): array
    {
        $values = [];

        foreach (self::BUILT_IN as $key => $fallback) {
            $values[$key] = $this->settings->get('defaults.'.$key, $fallback);
        }

        return $values;
    }

    /**
     * The attributes a brand new account starts with.
     *
     * @return array{timezone: string, work_days: string, work_start: string, work_end: string}
     */
    public function forUser(): array
    {
        return collect($this->all())->only(['timezone', 'work_days', 'work_start', 'work_end'])->all();
    }

    public function membersCanCreate(): bool
    {
        return (bool) $this->all()['members_can_create'];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        foreach (array_keys(self::BUILT_IN) as $key) {
            if (array_key_exists($key, $values)) {
                $this->settings->set('defaults.'.$key, $values[$key]);
            }
        }

        Audit::record('defaults.updated', 'Changed the defaults for new accounts and teams', null, $this->all());
    }
}
