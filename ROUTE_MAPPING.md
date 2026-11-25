# PanenIkan route mapping

PanenIkan uses PHP Native with procedural entry points. Protected pages and
their POST handlers load `includes/bootstrap.php`, which starts the session,
checks the authenticated role, and only then opens the database connection.

## Public routes

| URL | Method | Purpose | Access |
|---|---|---|---|
| `/` | GET | Uses the web server's `DirectoryIndex` (normally `/index.php`) | Public |
| `/app.php` | GET | Redirects to `/index.php` | Public |
| `/index.php` | GET | Redirects an administrator to the dashboard or a guest to login | Public |
| `/auth/login.php` | GET, HEAD | Displays the login form | Public |
| `/auth/login.php` | POST | Verifies credentials and the CSRF token | Public, CSRF required |
| `/assets/panenikan.css` | GET | Public stylesheet | Public |
| `/assets/panenikan.js` | GET | Local delete-confirmation and report-print behavior | Public |

## Authenticated routes and handlers

All pages below require a valid session with the `admin` role. Their state
changes are handled by POST on the same URL and require a valid CSRF token.

| URL | Methods and parameters | Purpose |
|---|---|---|
| `/dashboard.php` | GET, HEAD | Current user's and legacy-shared harvest/pond summary |
| `/panen.php` | GET, HEAD; optional `edit` ID. POST `action=simpan_panen` or `action=hapus_panen`, optional record `id` | List, add, edit, and delete harvest records |
| `/panen_foto.php` | GET, HEAD; required harvest `id` | Serves a stored harvest photo after authentication and ownership checks |
| `/ikan.php` | GET, HEAD; optional `edit` ID. POST `action=simpan_ikan` or `action=hapus_ikan`, optional record `id` | List, add, edit, and delete fish types |
| `/kolam.php` | GET, HEAD; optional `edit` ID. POST `action=simpan_kolam` or `action=hapus_kolam`, optional record `id` | List, add, edit, and delete ponds |
| `/laporan.php` | GET, HEAD; optional `tanggal`, `jenis`, and `kolam` filters | View the shared harvest report |
| `/auth/logout.php` | POST only, CSRF required | Ends the current authenticated session; other methods receive 405 |

Protected pages accept only GET, HEAD, or POST; unsupported methods receive 405.

## Internal files and access model

`/includes/` is denied by its existing `.htaccess`; `/config/`, `/database/`,
and `/scripts/` are denied by their directory rules. The root `.htaccess`
disables directory listings and denies direct access to dotfiles, SQL and shell
files, Markdown documents, and the unused root `layout.php` fragment. This
also protects `/schema.sql`, `/database/schema.sql`, the project readme, and
this route map. Internal PHP configuration and include files also reject
direct script requests. `/assets/` is the only static application directory
intended to be publicly served.

The schema has a `users` table but no role column. The application therefore
has one supported role, `admin`, assigned by the server only after successful
login. Ownership columns on harvest, pond, and fish-type records let new rows
belong to the authenticated user. Existing rows remain shared
(`owner_user_id IS NULL`). Reads, edits, deletes, and harvest references are
restricted to the current user's records or those shared legacy rows.

Apply `database/migrations/20261124_user_data_ownership.sql` once before
deploying the ownership-aware application code. It adds nullable ownership
columns without changing existing row values.

Harvest photos are stored outside the web document root in
`/var/www/panenikan-private/uploads/panen` by default. The PHP worker needs
write access to that directory; uploads are limited to one JPEG, PNG, or WebP
image of at most 5 MB per harvest record. The original client filename is never
used. Apply `database/migrations/20261124_panen_photo_upload.sql` once before
deploying photo-enabled code. Configure PHP with `upload_max_filesize=5M` and
`post_max_size=6M` and enable the `fileinfo` extension so the application-level
limit and MIME validation can run. The PHP `mbstring` extension is also
required for UTF-8 input-length validation.

The application uses procedural route handlers with a focused
`AccessControl` class for authorization. Its entry points are `public`; denial
helpers are `private`. No `protected` member is needed because the class is
`final` and the application has no authorization subclasses. Existing global
helper functions remain as compatibility wrappers around the class.

Apache must have `AllowOverride` enabled for the `.htaccess` protections to
take effect. The PHP role and login checks remain enforced by the application
independently of those directory rules.

## F/G security configuration

The application reads `PANENIKAN_ENV`, `PANENIKAN_URL`, and `PANENIKAN_DB_*`
values from process environment or from
`/var/www/panenikan-private/config.php` on the production server. The external
file must return an array and must not be committed. It should define
`PANENIKAN_ENV`, `PANENIKAN_URL`, and `PANENIKAN_DB_PASSWORD`; the database
host, user, and name default to `localhost`, `panenikan_app`, and `panenikan`.
Production URLs must use HTTPS. For local development, set
`PANENIKAN_ENV=local` and `PANENIKAN_URL` to the local application URL; only
local mode permits an HTTP session cookie. The root `.htaccess` redirects
non-local HTTP/IP requests to `https://www.devps.online`; ensure Apache has
`mod_rewrite` and `AllowOverride` enabled.

Behind a trusted TLS reverse proxy, set `PANENIKAN_TRUST_PROXY=1` so forwarded
`X-Forwarded-Proto: https` or `Forwarded: proto=https` is recognized. Do not
enable this setting unless the proxy is the only reachable upstream or has
already stripped/validated forwarded headers. For databases that support TLS,
set `PANENIKAN_DB_SSL_CA` to the CA certificate path and, when certificate
verification is required, `PANENIKAN_DB_SSL_VERIFY=1`; client key and
certificate paths may optionally be configured with
`PANENIKAN_DB_SSL_KEY` and `PANENIKAN_DB_SSL_CERT`.

Example production configuration (replace the password placeholder on the
server, never in the repository):

```php
<?php
return [
    'PANENIKAN_ENV' => 'production',
    'PANENIKAN_URL' => 'https://www.devps.online',
    'PANENIKAN_DB_HOST' => 'localhost',
    'PANENIKAN_DB_USER' => 'panenikan_app',
    'PANENIKAN_DB_PASSWORD' => 'replace-on-server',
    'PANENIKAN_DB_NAME' => 'panenikan',
    'PANENIKAN_AI_ENABLED' => '1',
    'PANENIKAN_AI_MODEL' => 'qwen2.5:0.5b',
];
```

Successful login/logout, failed login attempts, protected administrator
requests, authorization denials, and CRUD changes are written as structured
JSON logs outside the document root at
`/var/www/panenikan-private/logs`. Provision the directory for the PHP worker:

```sh
sudo install -d -o root -g www-data -m 0750 /var/www/panenikan-private
sudo install -d -o www-data -g www-data -m 0750 /var/www/panenikan-private/logs
sudo install -d -o www-data -g www-data -m 0750 /var/www/panenikan-private/uploads/panen
```

Create `/var/www/panenikan-private/config.php` with the example settings and
restrict it using `sudo chown root:www-data /var/www/panenikan-private/config.php`
and `sudo chmod 0640 /var/www/panenikan-private/config.php`.
`storage/.htaccess` also blocks access to old log/backup files that may remain
under the document root. Run `scripts/check_password_hashes.php` from the CLI
after configuring the database; it reports only counts of bcrypt, Argon2, and
other/unsupported password values, never the hashes themselves.

Install `scripts/panenikan-log-monitor.cron` as
`/etc/cron.d/panenikan-log-monitor` to run the rule-based monitor every five
minutes. Alerts are written to the system log and can be inspected with
`journalctl -t panenikan-monitor`. The optional G6 analysis uses Ollama only at
`127.0.0.1:11434`; it is triggered by rule alerts and receives aggregate event
counts only, not usernames, IP addresses, passwords, tokens, or raw logs.
Install and bind Ollama to loopback, and pull the model configured in the cron
file before enabling the scheduled monitor. The model's output is advisory only and
does not block accounts or execute commands.
