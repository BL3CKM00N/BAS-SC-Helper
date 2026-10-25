<p align="center">
  <img src="docs/readme-banner.svg" alt="BAS World" width="800">
</p>

# BAS SC Helper

A helper app for the service coordinator (SC) role at BAS World. It exists to make day-to-day service coordination easier: less manual work, fewer things to remember, everything in one place.

> **Status:** early stage. The base project, the installable app shell, the error and connection screens, the login and the admin panel are in place. The dashboard is still a placeholder. Features are added step by step and tracked in the [Roadmap](#roadmap).

## What it is

- A Laravel web app that can be installed on a phone or desktop as a PWA.
- Built around the workflow of a service coordinator. The feature list below grows as the workflow is mapped out.
- Everything is behind a login. Admins manage users in an admin panel (see [Access and admin](#access-and-admin)).

## Tech stack

| Part | Choice |
|---|---|
| Backend | Laravel 13, PHP 8.4+, Filament 5 (admin panel) |
| Frontend | Blade, Vite, Tailwind CSS 4 |
| Database | SQLite locally, PostgreSQL in production |
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
- **Roles:** the `roles` table holds `admin` and `user`, and every user has a `role_id`.
- **Dashboard:** a placeholder with a top bar. Admins also see an **Admin** tab, which opens the Filament admin panel at `/admin`. Normal users have no tab and get a 403 on `/admin`.
- **Admin panel:** Filament, always in dark mode, with the BAS World logo and green. It has no login of its own (visitors are sent to the login card). The **Users** resource creates and edits users, sets passwords (leave the field empty to keep the current one) and assigns roles. Nobody can delete their own account.
- **First admin:** `php artisan db:seed` (or `migrate --seed`) creates the roles and one admin from `ADMIN_MAIL` and `ADMIN_PASSWORD`. The defaults are `admin@example.com` and `CHANGEME!`. The seeder sets the name and password only when it creates the account, so it is safe to run on every deploy and a password changed in the panel is never overwritten. It does always restore the admin role for that email.
- **Change the default password:** set `ADMIN_MAIL` and `ADMIN_PASSWORD` in the Dokploy environment before the first deploy. If the default account already exists in production, sign in and change its password in the admin panel.
- **Microsoft SSO:** planned for later, restricted to `@basworld.com` accounts.

## Production

- **Database:** PostgreSQL. Production sets `DB_CONNECTION=pgsql` and the `DB_*` variables in the Dokploy app environment. The `.env` file is never committed.
- **Build and start:** nixpacks builds the app (the PHP and Node versions come from `composer.json` and `package.json`). `nixpacks.toml` runs the migrations and the (repeatable) seeder, publishes the Filament assets and starts the server on every deploy.
- **Proxy:** the app runs behind Traefik and Cloudflare, so proxies are trusted in `bootstrap/app.php`.
- **Caching:** the logo and favicon URLs carry a `?v=` version so Cloudflare never serves an outdated image. `/sw.js` is served with `no-cache`.

## PWA and connection handling

- **Install:** `public/manifest.webmanifest` plus the icons in `public/icons/` make the app installable. The favicon has no background (`public/favicon.svg`), the PWA icons use the dark brand colour so they look right on a home screen.
- **No zoom in the installed app:** when the app runs standalone, `resources/js/pwa.js` locks the viewport (no pinch or double-tap zoom) and the CSS disables pull-to-refresh bounce and the tap flash. Normal browser tabs keep regular zoom, so the site stays accessible there.
- **Offline screen:** a service worker (`resources/sw.js`, served at `/sw.js`) shows `public/offline.html` when a page cannot be loaded. It tells apart "no internet connection" and "can't reach the server" and reloads the page by itself when the server is back.
- **Live check while using the app:** `resources/js/connection.js` checks `/up` every second (every 10 s in a background tab) and on network changes. A failed check is retried after half a second, so the overlay appears 1 to 2 seconds after the connection drops. It shows an overlay when the connection drops and hides it again when it returns, without reloading the page or losing what you were doing.
- **Cache version:** `/sw.js` is generated per request. The server fills in a hash of the precached files (`offline.html`, logo, favicon, icon), so changing one of them automatically gives a new service worker and a new cache, and the `?v=` in the precache URLs stops Cloudflare from serving an outdated copy. Nothing needs to be bumped by hand.
- **Development look:** when `APP_ENV=local`, every page shows a red bold "DEVELOPMENT ENVIRONMENT" footer, a small red "DEV" label next to the logo (served as `/logo-dev.svg`, generated from `public/logo.svg`) and the page title ends with "[DEV]". The connection overlay and the offline page show the same markers: `/sw.js` is generated by the server and tells the service worker whether it runs in development. None of this appears in production.
- **Admin panel:** the Filament panel has its own layout, so it does not show the connection overlay or the development markers.
- **Error pages:** styled 403, 404, 419, 429, 500 and 503 pages live in `resources/views/errors/` and share the layout in `resources/views/layouts/app.blade.php`.

## Roadmap

Nothing here is promised yet. The list is filled in as the service coordinator workflow is mapped out.

- [x] Base Laravel project and deployment
- [x] BAS World branding, favicon and PWA icons
- [x] Offline, server-unreachable and error screens
- [ ] Map the service coordinator workflow and decide the first feature
- [x] Login, roles and a Filament admin panel for admins
- [ ] Sign in with Microsoft (only `@basworld.com` accounts)

## Conventions

- Commit messages are short and describe the change. No co-author lines.
- Keep this README up to date when features, setup steps or deployment details change.
