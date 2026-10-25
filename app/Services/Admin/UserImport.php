<?php

namespace App\Services\Admin;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Notifications\AccountInvite;
use App\Services\Teams\TeamManagement;
use App\Support\MailSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Creates many accounts from a CSV file with the columns name, email, role (optional) and team (optional).
 * Rows that cannot be created are skipped and reported, the rest still go through.
 */
class UserImport
{
    public const MAX_ROWS = 500;

    public const DELIVER_PASSWORD = 'password';

    public const DELIVER_EMAIL = 'email';

    public function __construct(private readonly TeamManagement $teams) {}

    /**
     * @return array{created: list<array{name: string, email: string, password: ?string}>, skipped: list<array{row: int, email: string, reason: string}>}
     *
     * @throws InvalidArgumentException when the file itself cannot be used
     */
    public function run(string $csv, string $delivery, User $actor): array
    {
        if ($delivery === self::DELIVER_EMAIL && ! MailSettings::canDeliver()) {
            throw new InvalidArgumentException('The app cannot send email yet. Use temporary passwords instead.');
        }

        $rows = $this->parse($csv);
        $roles = Role::query()->pluck('id', 'name');
        $created = [];
        $skipped = [];

        foreach ($rows as $number => $row) {
            $email = Str::lower(trim($row['email'] ?? ''));
            $name = trim($row['name'] ?? '');
            $roleName = Str::lower(trim($row['role'] ?? '')) ?: Role::USER;
            $teamName = trim($row['team'] ?? '');

            $reason = $this->problem($name, $email, $roleName, $teamName, $roles->all());

            if ($reason) {
                $skipped[] = ['row' => $number, 'email' => $email, 'reason' => $reason];

                continue;
            }

            try {
                $temporary = $delivery === self::DELIVER_PASSWORD ? Str::password(14, symbols: false) : null;

                $user = DB::transaction(function () use ($name, $email, $roles, $roleName, $temporary, $delivery, $teamName) {
                    $user = User::query()->create([
                        'name' => $name,
                        'email' => $email,
                        'password' => $temporary ?? Str::random(48),
                        'role_id' => $roles[$roleName],
                        'must_change_password' => $temporary !== null,
                        'invited_at' => $delivery === self::DELIVER_EMAIL ? now() : null,
                    ]);

                    if ($teamName !== '') {
                        $this->teams->addMember(Team::query()->where('name', $teamName)->firstOrFail(), $user);
                    }

                    return $user;
                });

                if ($delivery === self::DELIVER_EMAIL) {
                    $user->notify(new AccountInvite($actor));
                }

                $created[] = ['name' => $name, 'email' => $email, 'password' => $temporary];
            } catch (Throwable $exception) {
                $skipped[] = ['row' => $number, 'email' => $email, 'reason' => 'Could not be created: '.$exception->getMessage()];
            }
        }

        Audit::record('user.imported', 'Imported '.count($created).' account(s) from a CSV file ('.count($skipped).' skipped)', null, ['created' => count($created), 'skipped' => count($skipped)], $actor);

        return compact('created', 'skipped');
    }

    /**
     * @param  array<string, int>  $roles
     */
    private function problem(string $name, string $email, string $roleName, string $teamName, array $roles): ?string
    {
        if ($name === '' || Validator::make(['email' => $email], ['email' => 'required|email|max:255'])->fails()) {
            return 'Needs a name and a valid email address.';
        }

        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            return 'There is already an account with this email address.';
        }

        if (! isset($roles[$roleName])) {
            return 'Unknown role "'.$roleName.'". Use user, team_manager or admin.';
        }

        if ($teamName !== '' && ! Team::query()->where('name', $teamName)->exists()) {
            return 'There is no team called "'.$teamName.'".';
        }

        return null;
    }

    /**
     * @return array<int, array<string, string>> the rows keyed by their line number in the file
     *
     * @throws InvalidArgumentException
     */
    private function parse(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $lines = array_values(array_filter(preg_split('/\r\n|\n|\r/', (string) $csv), fn ($line) => trim($line) !== ''));

        if (count($lines) < 2) {
            throw new InvalidArgumentException('The file needs a header row (name, email) and at least one person.');
        }

        // Excel in some countries saves with semicolons.
        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(fn ($cell) => Str::lower(trim((string) $cell)), str_getcsv($lines[0], $delimiter, '"', ''));

        if (! in_array('name', $header, true) || ! in_array('email', $header, true)) {
            throw new InvalidArgumentException('The first row must contain the columns "name" and "email".');
        }

        if (count($lines) - 1 > self::MAX_ROWS) {
            throw new InvalidArgumentException('At most '.self::MAX_ROWS.' people per file.');
        }

        $rows = [];

        foreach (array_slice($lines, 1, null, true) as $index => $line) {
            $cells = str_getcsv($line, $delimiter, '"', '');
            $rows[$index + 1] = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
        }

        return $rows;
    }
}
