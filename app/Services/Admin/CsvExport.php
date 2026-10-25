<?php

namespace App\Services\Admin;

use App\Models\Team;
use App\Models\User;

/**
 * CSV files for the admin panel. Cells that start with a formula character are prefixed, so a name like
 * "=HYPERLINK(...)" cannot run anything when the file is opened in a spreadsheet.
 */
class CsvExport
{
    public function users(): string
    {
        $rows = User::query()->with(['role', 'teams'])->orderBy('name')->get()->map(fn (User $user) => [
            $user->name,
            $user->email,
            $user->role?->name,
            $user->teams->pluck('name')->implode('; '),
            $user->isDisabled() ? 'no' : 'yes',
            $user->last_login_at?->format('Y-m-d H:i'),
        ]);

        return $this->build(['name', 'email', 'role', 'teams', 'active', 'last_sign_in'], $rows->all());
    }

    public function teams(): string
    {
        $rows = Team::query()->with('manager')->withCount('users')->orderBy('name')->get()->map(fn (Team $team) => [
            $team->name,
            $team->manager?->name,
            $team->manager?->email,
            $team->users_count,
            $team->members_can_create ? 'yes' : 'no',
        ]);

        return $this->build(['team', 'manager', 'manager_email', 'members', 'members_can_create'], $rows->all());
    }

    /**
     * @param  list<string>  $header
     * @param  list<array<int, mixed>>  $rows
     */
    public function build(array $header, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, $header, ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($handle, array_map($this->safe(...), $row), ',', '"', '');
        }

        rewind($handle);

        return (string) stream_get_contents($handle);
    }

    private function safe(mixed $cell): string
    {
        $cell = (string) $cell;

        return $cell !== '' && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$cell : $cell;
    }
}
