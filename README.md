# Οικία / Oikia — building expense reporting

Laravel 13 API, Vue 3 frontend and MySQL 8.4. Greek is the default; English is selectable. Start with `FINAL_PLAN.md` for the domain rules and implementation ledger.

## Local application

The initial implementation is available at **http://127.0.0.1:5173** when both development servers are running. Its local administrator credentials are in `.local/admin-credentials.json` (private, ignored by version control). No resident accounts are provisioned automatically.

The imported archive covers April 2019–August 2026: 80 published months and 720 current apartment rows, from 76 source photos. November 2024–July 2025 is missing and remains a gap. Four combined statements produce eight explicitly labelled estimated months. Original photos are in private storage; corrections preserve earlier revisions and printed rounding differences have explicit explanations. See `docs/IMPORT_POLICY.md` for the October 2026 historical import and its reproducible private bundle.

### Requirements and setup

After the initial setup below, start all local services from this folder with:

```bash
./start.py
```

The launcher starts MySQL through Docker Compose, waits for it to be healthy, then starts the Laravel API and Vue development server. Open **http://127.0.0.1:5173** when it reports ready. It selects a compatible Node version from your PATH or installed NVM versions. Logs stay in the terminal; **Ctrl+C** stops both development servers and leaves MySQL running. Use `./start.py --check` to check prerequisites and application ports without starting services. To stop MySQL too, run `docker compose --env-file .local/compose.env stop db`.

PHP 8.3+ (tested with 8.4), Composer, PHP BCMath/PDO MySQL/mbstring/XML/cURL/ZIP extensions, Docker Compose for MySQL, and Node 20.19+ or a compatible newer version. This workstation has Node 20.19.5 at `/home/koykis/.nvm/versions/node/v20.19.5/bin`.

```bash
python scripts/init-local.py
docker compose --env-file .local/compose.env up -d db
cd backend
composer install
php artisan key:generate
php artisan migrate --seed
php artisan app:local-admin
php artisan serve --host=127.0.0.1 --port=8000
```

In another terminal, from the project root:

```bash
cd frontend
npm ci
npm run dev
```

`app:local-admin` only works in the local environment and will not overwrite an existing administrator. For a chosen email/password, use `php artisan app:admin your-email@example.com`; it prompts privately for the password. Reference seeding creates the nine apartments and categories, never financial history or resident accounts.

The API uses first-party session cookies through Sanctum, `/sanctum/csrf-cookie`, CSRF-protected writes, and backend role checks. The Vite proxy keeps development requests on one browser origin. There is no browser token storage.

### Validation

```bash
cd backend
php artisan test
composer validate --no-check-publish
cd ../frontend
npm test
npm run build
npx playwright test
```

Backend and frontend unit tests are isolated from the local MySQL data. Browser tests require the running local servers, private local admin credentials and imported pilot. They read the real pilot and create/delete one temporary draft revision, retaining its audit events. They do not modify a published statement. The browser configuration uses this workstation's installed Chrome; adjust `playwright.config.js` on another machine.

The host PHP currently emits pre-existing XSL/Imagick extension-version warnings. These extensions are not used by the application; application tests and builds run successfully. No system PHP configuration was changed.

## Administrator workflow

1. Choose **Μηνιαίες καταστάσεις / Monthly statements**, then create a month. Reporting month and issue date are separate.
2. Add sections and building expense lines. Categories describe spending; classifications separate operating, capital, reserve and unclassified entries.
3. Enter the apartment allocation grid exactly as printed. Boiler volume is **m³ of water**; boiler charge is **EUR**. Blank, explicit zero and unreadable are distinct states.
4. Attach the source photo/PDF privately. Rotation, zoom and a source pane beside the grids support visual review.
5. Save, review, resolve unreadable values and explain each non-zero reconciliation difference. Confirm visual review and publish. No automatic redistribution or billing takes place.
6. Correct a published month using **Create revision**. The previous publication remains in reports until the new revision is reviewed and published. Enable **Revision history** in the list to inspect superseded versions.

Greek decimal commas are accepted in entry fields; API totals use dot-decimal strings. Allocation source text retains its precision. Building line amounts and printed apartment allocations are two views of the same money, never added together.

The dashboard shows published data only. Missing months are gaps. Reserve collections are separate from expenses. Same-period previous-year comparisons require comparable published months; a zero denominator produces no percentage. Category trends and apartment details include table alternatives to charts.

### Importing older history

See `docs/IMPORT_POLICY.md` and `docs/CSV_FORMAT.md`. CSV uploads create drafts and track each imported, existing or invalid month; resume skips already imported records. Attach sources and review before publication. An existing period is never silently replaced.

The manually reviewed pilot transcription and its generator are private files under `.local/`. `app:import-pilot` is a one-time local onboarding utility, not a general OCR service. Its opt-in `--publish-reviewed` mode records the pilot's explicitly reviewed source rounding differences. Ordinary uploads never publish automatically.

### Interventions and savings

The intervention form offers source expense candidates such as repairs and maintenance. Link existing cost lines; a line cannot fund two projects. Record a completion date, target categories and a documented monthly/seasonal baseline with its source periods. A posting month is not automatically a completion date. Confirm suspected instalment links before grouping them.

Savings are signed estimates. Higher actual costs reduce savings; missing months, missing dates/baselines, superseded cost sources or overlapping category attribution prevent totals/payback from being shown. Partial completion months are excluded. No real payback result has been invented for the current pilot.

### Resident accounts

Use the Accounts screen only when ready to grant access. New forms default to inactive. An enabled resident sees their assigned apartment and building aggregates, never other apartment rows, raw documents or admin exports. Decide historical visibility before activating accounts; the current implementation grants the assigned unit's complete published history.

## Backup and recovery

```bash
python scripts/backup.py --verify-restore
```

This creates `.local/backups/<timestamp>/` with a consistent MySQL dump, private document archive, file hashes, application environment and verification receipt. Restore verification uses a new `building_restore_<timestamp>` database and a separate document directory; the live application is not overwritten. The drill database is retained for inspection. Backups contain private data and secrets and remain outside version control.

For actual recovery: stop application writes, restore the SQL dump into a fresh MySQL database, restore the private archive to `backend/storage/app/private`, configure the application for the restored database, retain the saved `APP_KEY`, run `php artisan migrate --force` if upgrading code, clear cached configuration, and verify counts, source downloads and login before switching traffic. Use a separate durable backup destination in production; the local backup is a tested development recovery copy.

## Deployment status

The administrator application is deployed on Playground at **http://167.233.105.254/building_manager/** as of 2026-10-02, using the server's existing Caddy routing. The deployment includes all 80 published months, 720 current apartment rows and the private source documents. Use the existing administrator credentials from `.local/admin-credentials.json`. No resident accounts were enabled.

The server files are in `/opt/building_manager`. `deploy/compose.playground.yaml` runs separate PHP-FPM, Nginx and MySQL containers, with persistent database and private storage and no additional public ports. The frontend is built with `--base=/building_manager/`; API calls, source URLs, CSV downloads and session cookies respect that path. Production debug mode is disabled and database passwords are specific to this deployment. Server secrets are in `.env.playground` and `shared/application.env`; private documents and sessions are in `shared/storage`.

Manage this deployment from `/opt/building_manager` with `docker compose --env-file .env.playground -f deploy/compose.playground.yaml ps` (or `up -d`). The route is in `/opt/filosafe/deploy/Caddyfile`, with its previous configuration backed up beside it. Validate/reload Caddy using the directory-mounted `/var/www/html/deploy/Caddyfile` path inside `filosafe-playground-caddy-1`; its individual `/etc/caddy/Caddyfile` bind mount was stale during deployment. `deploy/activate-playground.py` uses the current directory mount and verifies both applications after reload.

Deployment validation: 20 backend tests / 92 assertions and four frontend tests passed; the subpath frontend build passed. HTTP checks verified sign-in/out, CSRF enforcement, report coverage, C1's 80-month history, a private document checksum, CSV export and access restrictions. All 88 private files were verified after transfer. The existing `/filosafe/` application remained available. Chrome end-to-end tests were not run for this deployment, per the manager's instruction; browser review is manual. The private verification receipt is `.local/playground-deployment-_cuavblo/http-verification.json`.

Playground uses HTTP under the requested IP-based app path. Domain/TLS and a durable external backup destination remain future production setup work. `deploy/nginx.conf.example` remains a separate domain/TLS template.

For production: use `APP_ENV=production`, `APP_DEBUG=false`, an HTTPS `APP_URL`, `SESSION_SECURE_COOKIE=true`, production database secrets and the retained application key. Install Composer dependencies with `--no-dev --optimize-autoloader`, build the frontend, grant PHP access to its storage/cache directories, migrate, and provision the administrator interactively. Only the built frontend and Laravel's front controller should be served; source documents stay private. Do not run Vite or `artisan serve` as production servers.

## Design notes

Statement sections, expense lines, allocation cells and apartment rows are normalized MySQL records. Small per-row metric snapshots and project baseline settings use JSON columns, preserving historical weights and boiler m³ without extra schema tables. Project costs link to existing expense lines. Every publication selects one current revision per period; edits and corrections are audited. There is no allocation-rule engine until the manager confirms space-heating formulas.

Official references used for dependency selection: [Laravel 13 support policy](https://laravel.com/framework/docs/13.x/releases), [Sanctum](https://laravel.com/framework/docs/13.x/sanctum), [Vite requirements](https://vite.dev/guide/).
