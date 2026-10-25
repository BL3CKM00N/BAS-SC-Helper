<p align="center">
  <img src="docs/readme-banner.svg" alt="BAS World" width="800">
</p>

# BAS SC Helper

A helper app for the service coordinator (SC) role at BAS World. It exists to make day-to-day service coordination easier: less manual work, fewer things to remember, everything in one place.

> **Status:** early stage. The base project, the installable app shell, the login and admin panel, teams and the reminders (with push notifications) are in place. The dashboard is still mostly a placeholder. Features are added step by step and tracked in the [Roadmap](#roadmap).

## What it is

- A Laravel web app that can be installed on a phone or desktop as a PWA.
- Built around the workflow of a service coordinator. The feature list below grows as the workflow is mapped out.
- Everything is behind a login. Admins manage users and teams in an admin panel (see [Access and admin](#access-and-admin)).
- Reminders that keep nagging until you act, because a service coordinator can't always do things at a fixed time (see [Reminders](#reminders)).

## Tech stack

| Part | Choice |
|---|---|
| Backend | Laravel 13, PHP 8.4+, Filament 5 (admin panel) |
| Frontend | Blade, Vite, Tailwind CSS 4 |
| Database | SQLite locally, PostgreSQL in production |
| Notifications | Web push (`laravel-notification-channels/webpush`), sent by the Laravel scheduler |
| App shell | PWA (web manifest, service worker, offline screen) |
| Hosting | Self-hosted with Dokploy (nixpacks build), served over HTTPS at `bas.flashba.ng` |

## Local development

Requirements: PHP 8.4+, Composer, Node 22.

```bash
composer setup      # install dependencies, create .env, generate the key, migrate and seed, build assets
php artisan serve   # http://localhost:8000
```

For frontend work with hot reload, run `npm run dev` next to `php artisan serve`.

Locally the app uses SQLite (`database/database.sqlite`), so no database server is needed.

```bash
php artisan test    # run the tests
vendor/bin/pint     # fix code style
```

## Access and admin

- **Login card on `/`:** visitors who are not signed in only see a login card (email and password). Signed-in users go to the dashboard (`/dashboard`). Sign-in attempts are limited to 5 per minute for each email and IP address. Only `/up`, the login routes, `/sw.js` and the static files (manifest, icons, offline page) are public.
- **Roles:** the `roles` table holds `admin`, `team_manager` and `user`, and every user has a `role_id`. Only admins reach the admin panel. The `team_manager` role comes with a team (see Teams below).
- **Dashboard:** a placeholder with a top bar. Admins also see an **Admin** tab, which opens the Filament admin panel at `/admin`. Normal users have no tab and get a 403 on `/admin`.
- **Password and devices:** under **Account > Security** everyone can change their own password (the current one is needed; at least 10 characters) and sees **Devices you are signed in on**: browser and system, IP address and when it was last active, with this device marked. Each other device can be signed out on its own, **Sign out other devices** ends all but this one, and **Sign out everywhere** ends every session including this one. Changing the password signs out all other devices. All of these also end the "stay signed in" cookies. Sessions live in the `sessions` table (`SESSION_DRIVER=database`), and a session id is never shown on a page, only a keyed hash of it.
- **Navigation:** the header only holds the work pages (Dashboard, Reminders and, for people in a team, Teams). Everything about you is in the **profile menu**: the round button with your initials on a computer (a dropdown with your name, email and role), or, on a phone, one row at the bottom of the hamburger menu (your name and email) that unfolds the account links and folds again whenever the menu is reopened. It holds **Profile**, **Notifications**, **Work hours**, **Security**, the **Admin panel** (admins only) and **Sign out**. The menus close with the button, Escape, a tap outside or choosing a link.
- **Account pages (`/account`):** one topic per page, with the same four tabs on each: **Profile** (name, email, role and teams, read-only because an admin manages them), **Notifications** (push for this device), **Work hours** (working days, hours and time zone) and **Security** (change password and the devices list). `/settings`, the old single page, redirects to `/account`.
- **Notification hint:** reminders only reach you when notifications are on, so a device with notifications switched off gets a small dot on the profile button, an "Off here" label next to **Notifications** in the menu and a dismissible hint on the dashboard. Nothing is shown when the person blocked notifications on purpose or the device cannot show them. The admin panel's user menu has an **Account** link too.
- **Admin panel:** Filament, always in dark mode, with the BAS World logo and green. Its greys match the app (page `#1d1d1a`, sidebar and cards `#262623`, the colour of the app header). On a computer there is no top bar: the logo sits at the top of the sidebar and the profile menu (Account, Dashboard, Sign out) at the bottom of it. On a phone (below Filament's 1024 px breakpoint) the panel gets a bar like the app header, in the sidebar colour, with the logo on the left and a hamburger on the right that opens the sidebar. It has no login of its own (visitors are sent to the login card). The **Users** resource creates and edits users, sets passwords (leave the field empty to keep the current one) and assigns roles. Nobody can delete their own account.
- **First admin:** `php artisan db:seed` (or `migrate --seed`) creates the roles and one admin from `ADMIN_MAIL` and `ADMIN_PASSWORD`. The defaults are `admin@example.com` and `CHANGEME!`. The seeder sets the name and password only when it creates the account, so it is safe to run on every deploy and a password changed in the panel is never overwritten. It does always restore the admin role for that email.
- **Change the default password:** set `ADMIN_MAIL` and `ADMIN_PASSWORD` in the Dokploy environment before the first deploy. If the default account already exists in production, sign in and change its password in the admin panel.
- **Requests:** a manager cannot change who is in the team. On the team page they send a request: **a new account** (name and email), **adding an existing user** (by email) or **removing a member**. Admins see them under **Team requests** in the admin panel (with a badge for the pending ones and a push notification if they enabled notifications), and approve or reject them, with a reason that the manager sees. The manager can withdraw a request that is still pending. Approving carries it out.
- **New accounts:** when approving a new account the admin chooses how the person gets a password. **Temporary password:** it is shown once to the admin, who hands it over, and the person must choose their own on first sign-in. **Email invitation:** the person gets a mail with a link, valid for 7 days and usable once, to choose a password. This needs mail settings (`MAIL_*`, see `.env.example`); in production the option is disabled until `MAIL_MAILER` is something other than `log`, and if the mail server fails nothing is created.
- **Admin panel menu and audit log:** the panel menu is grouped into **People** (Users, Teams, Team requests), **Monitoring** and **System**. Everything that changes people, teams or requests is written to the **Audit log** (Monitoring): who, what and when, with the name kept even if the account is deleted later. Entries are kept for 400 days. Settings that an admin changes in the panel are stored in the `app_settings` table (`App\Services\Admin\AppSettings`).
- **Admin dashboard:** shows **Needs attention** (pending requests, open teams, people without a team, unused invitations, temporary passwords not changed) and **System health** (the scheduler writes a heartbeat every minute through `reminders:send` and shows red after 3 minutes without one, database, push devices, failed pushes). **System info** (System menu) lists versions and drivers.
- **Managing people (admin panel, Users):** filter by role, team, disabled, without a team or never signed in; the list shows whether an account is active and the last sign-in. Per person (row menu and edit page): **Disable / Enable account** (signed out everywhere on the next request, no sign-in, no reminders), **Reset password** (temporary password shown once, must be changed), **Sign out everywhere**, **Resend invitation**, and **Remove person** (type their email to confirm: name and email are wiped, they leave all teams and lose devices and personal reminders; team reminders and the audit log stay). The last admin who can still sign in cannot be disabled, removed, deleted or demoted, and nobody can disable themselves. All of it is written to the audit log.
- **Active sessions and sign-in log (admin panel, Monitoring):** *Active sessions* lists every signed-in device of every person (browser, system, IP, last active) and lets an admin end one. Session ids are never shown, only a keyed hash. The *Sign-in log* records every successful and failed sign-in (email, IP, device, reason) and is pruned after 90 days by `model:prune`, which is scheduled daily; the audit log is pruned after 400 days.
- **Maintenance mode (admin panel, System):** a switch with a message and an expected-back time, kept in the database (not `artisan down`), so admins can still sign in and the scheduler keeps running: **reminders and push notifications keep being sent**. Everybody else who is signed in sees a locked maintenance page (HTTP 503 with `Retry-After`) that opens the app again by itself when maintenance ends; the login page shows a notice and only admins can sign in; open pages get a "Under maintenance" overlay through the health check (`/up` gets an `X-Maintenance` header). Admins see a banner. From a shell: `php artisan maintenance:on --message="..." --minutes=30` and `php artisan maintenance:off`.
- **Announcements (admin panel, System):** a message for everyone, one team, or all team managers, with a severity (information, warning, critical), an optional start and end time and an optional push notification (sent once, by `announcements:publish`, which runs every minute). It shows as a banner under the app header, refreshes every minute and can be dismissed per person, except critical ones, which stay until they end.
- **Notification reach and team activity (admin panel, Monitoring):** *Notification reach* shows how many people have push notifications on, the number of devices, failed pushes, who has no device yet (the default filter) and lets an admin send a test notification to a person. *Team activity* shows per team the members, open and overdue team reminders and how many were finished in the last 7 days. Personal reminders are private: admins never see their content, only team reminders are counted.
- **Bulk tools and app defaults (admin panel):** on *Users*, **CSV > Import people** creates many accounts from a CSV file (columns `name`, `email`, optional `role` and `team`; commas or semicolons, at most 500 rows). Rows that cannot be created are skipped and listed. With temporary passwords the admin gets a file with the passwords, shown only once; with email invitations everybody gets a link. **Export people** and **Export teams** download CSV files (cells that start with a formula character are neutralised). In the users table, select people and use **Add to team** or **Remove from team**. *App defaults* (System) sets the time zone and work hours new accounts start with and whether members may create team reminders in new teams. Existing accounts and teams are never changed by it.
- **Microsoft SSO:** planned for later, restricted to `@basworld.com` accounts.
- **Teams:** a user can be in several teams, or none. Each team has **at most one manager** (`teams.manager_id`), and a manager manages **exactly one team**:
  - **Getting the role creates the team.** An admin gives someone the `team_manager` role in the admin panel and a team ("Anna's team", renamable) is created with them as its manager.
  - **Losing the role opens the team.** If the role is revoked, or the manager is deleted, the team has no manager and shows as **Open**. The former manager stays in it as a normal member. An admin can open the team and use **Assign manager** to give it to someone (who then takes over this team instead of getting a new one).
  - **Empty open teams disappear.** An open team that nobody is in any more is deleted automatically (right away, and `teams:prune` checks every 5 minutes). Its team reminders go with it.
  - **A manager only manages their own team:** renaming it, deciding whether members may create team reminders, and sending requests (below). Admins manage teams and memberships directly in the admin panel (**Teams**, **Users**).
  - **The Teams menu item only shows for people who are in a team.**

## Reminders

Two kinds, both created on the **Reminders** page:

- **Deadline:** a title and a due date and time. It starts reminding a while before (you choose 30 minutes to 1 day) and keeps going until it is marked done. The reminders speed up by themselves: every 60 minutes while more than 2 hours are left, every 30 minutes in the last 2 hours, every 10 minutes in the last 30 minutes and every 5 minutes once overdue. You can set a fixed interval instead.
- **Repeats during the day:** for things you do several times a day. The timer is rolling: the next reminder comes X minutes after you last marked it done, not at fixed clock times. An optional daily target ("4 times a day") pauses it until the next working day once reached. If you ignore it, it nags every 10 minutes (or your own interval).

**What you can do with a reminder:** **Done**, **Snooze** (10 minutes, 30 minutes or 1 hour) or **Skip today** (hidden until the next working day; it asks for confirmation first, Done and Snooze do not). The **Due now** list on the dashboard and on the reminders page refreshes every 30 seconds and after each action, shows the number in the tab title and vibrates and beeps when something new shows up.

**Work hours:** each user sets working days, start and end of the day and a time zone under **Account > Work hours** (default Monday to Friday, 08:00 to 17:30, Europe/Amsterdam). Nothing is sent outside them: reminders that come due later wait for the next morning. A deadline can optionally keep nagging when it is overdue, even outside work hours.

**Personal and team reminders:**

| | Personal | Team |
|---|---|---|
| Who sees it | Only the owner (not even admins) | All members of the team |
| Who creates it | Anyone | Managers, and members if the team allows it |
| Who edits or deletes it | The owner | The creator or a manager |
| Who gets it | The owner | One chosen member, or everyone in the team |

When a team reminder goes to everyone you choose: **anyone can finish it** (the first Done finishes it for everyone, a repeating reminder then resets for everyone) or **everyone does it themselves** (each member marks it done, and snoozes and work hours are per person).

**How it works:** every person who gets a reminder has their own next notification time (`reminder_recipients`). The scheduler runs `php artisan reminders:send` every minute. It notifies everyone whose time has come and who is inside their work hours, marks them as pending (so the reminder stays in the Due now list until they act) and plans the next nag. Every notification and action is logged in `reminder_events`, which drives the daily counts. The rules live in `app/Services/Reminders/` (`ReminderEngine`, `WorkHours`).

### Push notifications

- **Per device:** turn it on under **Account > Notifications** on each phone or computer. The notification has **Done** and **Snooze 10 min** buttons. They call signed URLs that expire after 12 hours, so they work from the lock screen without opening the app.
- **iPhone:** web push only works for the installed app (iOS 16.4 or newer: Share, Add to Home Screen, then open it from there), and iOS does not show the action buttons, so tapping the notification opens the app. The in-app Due now list is the source of truth when a push is delayed, for example by a phone's battery saver.
- **Keys:** generate the VAPID keys once with `php artisan webpush:vapid` (it writes `VAPID_PUBLIC_KEY` and `VAPID_PRIVATE_KEY` to `.env`) and set the same two variables in the Dokploy environment, plus `VAPID_SUBJECT` (the site URL). Never commit them. Changing the keys drops all device subscriptions. Without keys the app still works, only the pushes are skipped.
- **Locally:** run `php artisan schedule:work` next to `php artisan serve` (or run `php artisan reminders:send` by hand) to test.

## Production

- **Database:** PostgreSQL. Production sets `DB_CONNECTION=pgsql` and the `DB_*` variables in the Dokploy app environment. The `.env` file is never committed.
- **Build and start:** nixpacks builds the app (the PHP and Node versions come from `composer.json` and `package.json`). `nixpacks.toml` runs the migrations and the (repeatable) seeder, publishes the Filament assets, starts the scheduler in the background (`schedule:work`, which runs `reminders:send` every minute) and starts the server on every deploy.
- **Environment variables:** besides the database, set `ADMIN_MAIL`, `ADMIN_PASSWORD`, `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY` and `VAPID_PRIVATE_KEY` in Dokploy (see `.env.example`), and the `MAIL_*` settings if admins should be able to send email invitations (see `.env.example`).
- **Proxy:** the app runs behind Traefik and Cloudflare, so proxies are trusted in `bootstrap/app.php`.
- **Caching:** the logo and favicon URLs carry a `?v=` version so Cloudflare never serves an outdated image. `/sw.js` is served with `no-cache`.

## PWA and connection handling

- **Install:** `public/manifest.webmanifest` plus the icons in `public/icons/` make the app installable. The favicon has no background (`public/favicon.svg`), the PWA icons use the dark brand colour so they look right on a home screen.
- **No zoom in the installed app:** when the app runs standalone, `resources/js/pwa.js` locks the viewport (no pinch or double-tap zoom) and the CSS disables pull-to-refresh bounce and the tap flash. Normal browser tabs keep regular zoom, so the site stays accessible there.
- **Offline screen:** a service worker (`resources/sw.js`, served at `/sw.js`) shows `public/offline.html` when a page cannot be loaded. It tells apart "no internet connection" and "can't reach the server" and reloads the page by itself when the server is back.
- **Live check while using the app:** `resources/js/connection.js` checks `/up` every second (every 10 s in a background tab) and on network changes. A failed check is retried after half a second, so the overlay appears 1 to 2 seconds after the connection drops. It shows an overlay when the connection drops and hides it again when it returns, without reloading the page or losing what you were doing.
- **Cache version:** `/sw.js` is generated per request. The server fills in a hash of the precached files (`offline.html`, logo, favicon, icon), so changing one of them automatically gives a new service worker and a new cache, and the `?v=` in the precache URLs stops Cloudflare from serving an outdated copy. Nothing needs to be bumped by hand.
- **Development look:** when `APP_ENV=local`, every page shows a red bold "DEVELOPMENT ENVIRONMENT" footer, a small red "DEV" label next to the logo (served as `/logo-dev.svg`, generated from `public/logo.svg`) and the page title ends with "[DEV]". The connection overlay and the offline page show the same markers: `/sw.js` is generated by the server and tells the service worker whether it runs in development. The admin panel has the same markers: the red footer (next to the sidebar on a computer), "[DEV]" in the tab title and the DEV label on the logo in the sidebar and in the phone bar. None of this appears in production.
- **Admin panel:** the Filament panel has its own layout, so it does not show the connection overlay. It does show the development markers.
- **Error pages:** styled 403, 404, 419, 429, 500 and 503 pages live in `resources/views/errors/` and share the layout in `resources/views/layouts/app.blade.php`.

## Roadmap

Nothing here is promised yet. The list is filled in as the service coordinator workflow is mapped out.

- [x] Base Laravel project and deployment
- [x] BAS World branding, favicon and PWA icons
- [x] Offline, server-unreachable and error screens
- [x] Teams with a single manager each, open teams, and requests that admins approve
- [x] Reminders: deadlines, repeating reminders, work hours, team reminders and push notifications
- [ ] Map the rest of the service coordinator workflow and decide the next feature
- [x] Login, roles and a Filament admin panel for admins
- [ ] Sign in with Microsoft (only `@basworld.com` accounts)

## Conventions

- Commit messages are short and describe the change. No co-author lines.
- Keep this README up to date when features, setup steps or deployment details change.
