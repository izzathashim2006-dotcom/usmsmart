# USMSmart

Card-management portal for UDA Transporter Studio, Kampus Kejuruteraan USM. One
PHP entry point serves the login screen, the dashboard, the JSON actions and the
printable card views; the browser code lives in `assets/` and is built ahead of
time.

## What is here

| Path | Purpose |
| --- | --- |
| `index.php` | Front controller: bootstrap, session/CSRF guards, route dispatch |
| `app/functions.php` | Shared helpers (backups, serials, Sysweb store, audit) |
| `controllers/` | One file per route group: auth, sysweb, dashboard API, system probes, reports, people, access, cards, sessions, backups |
| `views/login.php` | Login and first-run administrator setup |
| `views/dashboard.php` | Dashboard markup, modals and the user manual |
| `assets/usmsmart-dashboard.js` | Dashboard behaviour and rendering |
| `assets/usmsmart-dashboard.css` | Hand-written dashboard layer (tokens, rail, panels) |
| `assets/usmsmart-login.css` | Login screen |
| `assets/tailwind.css` | Compiled Tailwind output — generated, do not edit |
| `assets/vendor/` | Chart.js, html5-qrcode, Font Awesome subset, local fonts |
| `config/` | Database config template; the real file is gitignored |
| `database/schema.sql` | Structure of every application table, no rows |
| `tools/` | Verification and build scripts |

## Requirements

- PHP 8.2 with `pdo_mysql` (XAMPP's bundled PHP is what this was built on)
- MySQL 8 or MariaDB 10.6+
- Apache 2.4 with `mod_deflate`, `mod_expires`, `mod_headers` and `.htaccess`
  overrides enabled
- Node 22+ — only for rebuilding CSS and running the verification tools; not
  needed on the production server

Optional: `pdo_sqlsrv` if the Sysweb live snapshot is enabled.

## First run

1. Create the database and load the schema:

   ```sh
   mysql -u root -p -e "CREATE DATABASE usmsmart_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   mysql -u root -p usmsmart_db < database/schema.sql
   ```

   The application also creates missing tables at boot, so an empty database
   works too; the dump is the reviewable version of the same structure.

2. Copy the config template and fill in the local values:

   ```sh
   copy config\database.example.php config\database.local.php
   ```

   `config/database.local.php` is gitignored and `config/.htaccess` returns 403
   for direct requests, so credentials never leave the server.

3. Open `http://localhost/USM/`. With no accounts present the page offers the
   one-time administrator setup form (12-character minimum password).

## Configuration

`config/database.local.php` returns an array:

```php
return array(
    'host' => 'localhost',
    'database' => 'usmsmart_db',
    'username' => 'root',
    'password' => '',
);
```

Environment variables are the fallback when that file is absent:
`USM_DB_HOST`, `USM_DB_NAME`, `USM_DB_USER`, `USM_DB_PASS`.

The Sysweb read-only live snapshot reads its credentials from **outside the web
root**, `C:/xampp/private/usmsmart_sysweb.php`, or from the path in
`USMSMART_SYSWEB_CONFIG`. Missing or placeholder values simply disable the
snapshot; the rest of the portal keeps working.

## Front-end assets

Everything is served locally; there are no runtime CDN calls.

```sh
npm install          # once
npm run build:css    # after editing markup or Tailwind classes
node tools/build-vendor-css.js   # after adding an icon or font weight
```

`build:css` compiles `tailwind.input.css` against `index.php`, `views/**` and
`assets/*.js` into `assets/tailwind.css`. `build-vendor-css.js` re-downloads
Font Awesome 6.4.0 and the Google fonts, then regenerates
`assets/vendor/font-awesome.css`, `assets/vendor/fonts.css` and
`assets/vendor/fonts/*.woff2`. Both outputs are versioned by `filemtime()`, so a
rebuild is picked up immediately and cached for a year.

## Verification

Start Apache and MySQL, create a throwaway account, then:

```sh
USM_USER=audit USM_PASS=... node tools/smoke-test.js   # renders, payloads, keyboard, mobile, both themes
USM_USER=audit USM_PASS=... node tools/axe-audit.js    # WCAG 2.1 A/AA via axe-core, login + 5 dialog states
node tools/tailwind-coverage.js                        # every class used in markup exists in compiled CSS
```

`smoke-test.js` exits non-zero on a console error, a failed request, a missing
modal, broken keyboard behaviour or horizontal overflow at 390px.
`axe-audit.js` exits non-zero on any serious or critical violation.

`tools/style-snapshot.js` writes a computed-style baseline for a page;
`tools/style-diff.js` compares two runs. Useful for catching unintentional
visual changes, noisy for large DOM shifts.

Remove any audit account afterwards — nothing in these tools writes to the
database, but the account itself is real.

## Backup and restore

**In-app.** Administrators can run a verified backup from the dashboard. Backups
are written to `<parent of the project folder>/usmsmart_private_backups` by
default — outside the document root — and every action is logged to
`usmsmart_audit_log`. Set `USMSMART_BACKUP_DIR` to use a different location; the
built-in `.htaccess` denial is applied only when that override points back inside
the web root. The CLI scheduler uses the same variable.

**Manual (recommended before schema work):**

```sh
mysqldump -u root -p --single-transaction --routines --triggers usmsmart_db > usmsmart_db_YYYYMMDD.sql
```

Store the dump outside the web root — `*.sql` is gitignored, but Apache would
still serve it from the project folder.

**Restore, safely:**

1. Restore into a new database first: `CREATE DATABASE usmsmart_restore;`
   then `mysql -u root -p usmsmart_restore < dump.sql`.
2. Point `config/database.local.php` at the restored database temporarily and
   check login, the student list, the audit log and one Sysweb export.
3. Only then rename databases, or re-import into the original one.
4. Keep the pre-restore dump until the restored system has been used for a day.

## Layout conventions

- `index.php` is the only entry point. It defines `USM_ROOT`, starts the
  session, requires `app/functions.php`, then requires the controller that owns
  the current action. Controllers are included in the front controller's scope,
  so they read `$action`, `$pdo`, `$authReady` and `$_SESSION` directly.
- Actions are JSON endpoints in `controllers/routes-*.php`; they answer and
  `exit`, and never render HTML. Helpers live in `app/functions.php` so every
  controller can call them regardless of request order.
- All writes go through the global CSRF guard for authenticated POSTs.
- Sessions are per-tab (`USMBOOT` / `USMTAB_<hash>`), HttpOnly, SameSite=Lax,
  regenerated on login, and expire after 30 minutes of inactivity.
- Login throttling keeps two budgets over the same 15-minute window: five
  failures for one username/source pair, and thirty for the source across all
  usernames, so rotating usernames cannot reset the budget. Both key on
  `REMOTE_ADDR`; behind a reverse proxy every client shares the proxy address
  and therefore one budget, so raise the limit or resolve the real client IP at
  the proxy. Exhausting either budget writes a `login_throttled` audit row once,
  not on every blocked retry.
- The dashboard has one modal keyboard layer (`data-modal-close` on each
  dialog) that handles Escape, the Tab trap and focus return.
- The root `.htaccess` refuses `.git/`, `tools/` and `database/` over HTTP and
  blocks direct requests for `sqlserver_connection.php` and the backup
  scheduler. Both are includes or CLI jobs, never endpoints. Keep scratch
  scripts and old copies of the app outside `htdocs`.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Blank page | `C:\xampp\apache\logs\error.log`; usually a missing `config/database.local.php` or a MySQL login failure |
| Unstyled or stale page | `npm run build:css`, then hard-reload; the stylesheet URL is keyed by file modification time |
| Missing icons | `node tools/build-vendor-css.js` — the committed Font Awesome sheet only contains referenced icons |
| 403 on `/config/...` | Expected: the directory is denied by `config/.htaccess` |
| Dashboard empty after login | `usmsmart_users.is_active` and `auth_version`; a reset increments the version and signs the account out everywhere |
