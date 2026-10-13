# Attendify on Vercel Hobby

## Decision and release gate

The HTTP application is a candidate for the maintained community runtime, not a verified deployment yet. No Vercel project, production environment, database, or deployment was changed. Keep the existing portfolio project separate.

The lockfile contains Laravel 13.35.0, Livewire 4.4.7, Flux 2.20.1, Blaze 1.0.19 and Dompdf 3.1.6. Symfony 8 requires PHP >=8.4, even though composer.json allows 8.3. Use the pinned `vercel-php@0.9.0` runtime (PHP 8.5) with Node 22.x. CI now uses PHP 8.5. Production Composer dependencies pass the local PHP 8.4 platform check; the build hook repeats the check using the actual runtime, because its installer otherwise ignores platform requirements.

Local validation completed: composer.json valid; production platform requirements passed; Pint passed after formatting-only corrections in three existing files; PHPStan passed with zero errors; full suite 152 passed, 2 skipped, 583 assertions. Three new subprocess tests exercise the actual PHP entry point for health, login/assets behind an HTTPS proxy, and unauthenticated Admin redirects. The Vite build and public-asset publication test passed. PHP syntax checks passed. Local PHP was 8.4.0 and Node was 24.16.0; PHP 8.5/Node 22 parity and the actual Vercel runtime still require CI/preview verification. Production package files total approximately 49.9 MiB locally, excluding autoload metadata and the PHP runtime; this is not the deployed bundle size. No external database, mail delivery or Vercel deployment was tested.

Sources checked October 13, 2026:

- [Runtime versions, extensions and Composer hooks](https://github.com/vercel-community/php)
- [Runtime build implementation](https://github.com/vercel-community/php/blob/master/src/index.ts)
- [Runtime Composer implementation](https://github.com/vercel-community/php/blob/master/src/utils.ts)
- [Vercel function limits](https://vercel.com/docs/functions/limitations)
- [Build limits](https://vercel.com/docs/limits)
- [Hobby personal/non-commercial use](https://vercel.com/docs/plans/hobby)

Builds have a 45-minute ceiling. Budget below the standard 250 MB uncompressed function limit, including PHP binaries/libraries and production vendor dependencies. Actual packaged size must be checked in the Vercel build; local vendor includes development dependencies and does not establish that size. Do not assume the large-functions beta works with this community runtime. Published Fluid Node/Python duration limits are not proof of PHP runtime behavior; verify the effective duration in the built function. The PHP launcher buffers responses: do not assume Laravel StreamedResponse bypasses the 4.5 MB request/response ceiling. Livewire downloads also encode their contents, increasing payload size. The frontend build passed locally, but remote Composer/build time and function behavior still need verification.

**Blockers for unrestricted use:** Vercel cannot host a persistent Laravel queue worker. Employee invitations implement ShouldQueue, encrypted payloads, retries, backoff and after-commit delivery. Keep QUEUE_CONNECTION=database and run a worker externally to preserve that behavior. A demo can explicitly choose sync delivery, but it loses durable retry/backoff behavior and adds request latency; it is not equivalent. Reports have a 366-day date bound but no employee-count/output-byte bound, so large valid CSV/PDF exports can exceed Vercel's payload or execution limits. No report capability was removed. For unrestricted exports, use an external worker plus private object storage with short-lived download URLs, or host the Laravel backend on a service supporting workers and streaming. Those alternatives are proposals, not implemented integrations.

## Prepared files and request flow

`vercel.json` builds frontend assets first and stages only public assets in `vercel-static`. PHP source, dotfiles, the Vite hot marker and local uploads are not published. Filesystem assets are served first; every remaining route and method reaches `api/index.php`, including auth, signed email links, Livewire JavaScript, component assets and update POST requests. Public index.php is explicitly routed to the function. The function keeps Laravel's public path at `public`, so the Vite/font manifests remain available to Laravel in its bundle.

The entry point initializes writable temporary storage before Laravel loads. Blade, Livewire single-file components, Blaze and Dompdf use disposable scratch space there; sessions and cache must be in the external database. No config/route/view optimization cache is shipped from the developer machine. The runtime's Composer hook creates build directories and performs package discovery. Do not run migrations, seeders, key generation or composer setup in a Vercel build.

Proxy trust applies only when VERCEL=1 and relies on Vercel sanitizing forwarded headers. Secure cookies and stderr logging default on for that environment. Existing local settings and business routes remain intact. `/debug-test` is an existing diagnostic route writing temporary logs; remove or restrict it before exposing a real production application. No uploads, persistent report files, scheduled jobs, broadcast server or custom background jobs were found beyond employee invitation notifications. `routes/console.php` defines only the inspire command. Future uploads need private S3-compatible storage and its Flysystem adapter; the current S3 configuration alone does not install that adapter. Future scheduling needs an external Laravel scheduler; daily Hobby cron is not a general replacement for minute-level schedule:run.

## External database and mail

Use real MySQL 8.0.16+ (prefer current MySQL 8) with InnoDB, foreign keys, transactions, row locks, unique generated columns, enforced CHECK constraints and CREATE TRIGGER privileges for migrations. Attendify's attendance and leave audit immutability depends on these triggers. A generic MySQL-compatible service that forbids triggers is unsuitable. Use DB_CONNECTION=mysql; the current migrations do not apply the MySQL protections when the driver is mariadb, so do not switch drivers without verifying equivalent protections.

[Aiven advertises a free MySQL tier](https://aiven.io/docs/products/mysql/concepts/mysql-free-tier), but it is only a candidate: verify privileges, TLS, connections, service availability and migration behavior on your instance before selecting it. The Vercel plan does not include this database. Provision a separate demo database and use synthetic records. Choose a nearby function region after choosing the database region. Vercel Hobby has no guaranteed static egress IP; database network rules must permit its connections without relying on a fixed function IP.

Use verified TLS. MYSQL_ATTR_SSL_CA accepts a path, not PEM contents. For a provider-specific CA, place its public CA certificate in `certs/mysql-ca.pem` and configure MYSQL_ATTR_SSL_CA=/var/task/user/certs/mysql-ca.pem for this runtime. For local migrations use a local certificate path instead. Never disable certificate verification; confirm the resulting connection is encrypted using SHOW SESSION STATUS LIKE 'Ssl_cipher'. Use a limited application user, and a separate migration user when possible.

Configure a real TLS SMTP transport and verified sender (MAIL_MAILER=smtp, provider host/port/scheme/credentials). The default log mailer does not deliver invitations or password resets and may log sensitive links. Resend/Postmark options require additional Composer transport dependencies; none were added. Start the external worker from this same release with the same APP_KEY and database: `php artisan queue:work database --sleep=3 --tries=3 --timeout=60`. Monitor failed jobs and restart workers after releases. No worker is started by the prepared Vercel files.

## Environment variables

Enter these directly in the **new Attendify project** settings. No secrets belong in vercel.json, Git, build output or VITE_* variables. Do not upload any .env files. Preserve one generated APP_KEY across deployments; changing it breaks encrypted data and queued invitations.

```text
APP_NAME=Attendify
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-STABLE-ATTENDIFY-HOST
APP_KEY=<generate privately with php artisan key:generate --show>
DB_CONNECTION=mysql
DB_HOST=<external host>
DB_PORT=<provider port>
DB_DATABASE=<demo database>
DB_USERNAME=<application user>
DB_PASSWORD=<secret>
MYSQL_ATTR_SSL_CA=/var/task/user/certs/mysql-ca.pem
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true
SESSION_COOKIE=attendify-session
CACHE_STORE=database
QUEUE_CONNECTION=database
LOG_CHANNEL=stderr
LOG_LEVEL=warning
LOG_DEPRECATIONS_CHANNEL=stderr
MAIL_MAILER=smtp
MAIL_SCHEME=<provider TLS scheme>
MAIL_HOST=<provider host>
MAIL_PORT=<provider port>
MAIL_USERNAME=<secret>
MAIL_PASSWORD=<secret>
MAIL_FROM_ADDRESS=<verified sender>
MAIL_FROM_NAME=Attendify
```

Leave SESSION_DOMAIN, ASSET_URL and DB_URL unset unless deliberately required. APP_URL must match the stable HTTPS host for mail links and passkeys. Do not share cookies with the portfolio. Use a separate preview database, key and stable preview host; signed links and passkeys are origin-sensitive. Keep deployment protection on during verification. The template documents settings; it does not change your local .env or remote settings.

## Exact next steps (gated)

1. Review the prepared files and existing uncommitted middleware change separately. Use PHP 8.4+ locally (8.5 for parity), Node 22.12+ within Node 22.x, Composer and npm. Run `composer validate`, `composer check-platform-reqs --no-dev`, `composer ci:check`, `npm ci`, `npm run build`, `node scripts/vercel-static.mjs`, and `node --test tests/vercel-static.test.mjs`. Resolve failed checks before deployment. Existing PHP tests use in-memory SQLite only for testing; production never uses it.
2. Provision the external MySQL demo database, CA and real mail service. Choose an external worker to preserve invitations. From a trusted workstation or reviewed CI, inject database/key settings securely, run `php artisan migrate --force` against that new database, then `php scripts/verify-attendance-mysql.php`. Never use migrate:fresh. Verify leave audit triggers as well as attendance triggers. Run business tests against a separate disposable MySQL test database to verify concurrency and MySQL-specific behavior. Do not point RefreshDatabase tests at the demo/production database. Create demo accounts privately; the existing DatabaseSeeder is not an admin provisioning workflow.
3. Once backend tests and infrastructure compatibility pass, import the repository as a **new** Vercel project named attendify, Framework Other, root repository directory, Node 22.x. Honor the install/build/output settings in vercel.json. Do not relink or alter the portfolio project. Set the environment values above in the appropriate scopes. Connect previews to isolated data, not production data.
4. Build and test a protected preview before a production release. Inspect logs for Composer platform success, package discovery, bundle size and effective duration. Verify HTTPS /up and root/login redirects, built CSS/fonts/passkey assets, Livewire JS/component assets, login/logout/session continuity across cold starts, role restrictions, deactivated accounts, CSRF rejection, password reset, email verification, invitations/queue retries, two-factor authentication and passkeys on the stable origin.
5. Exercise every Admin/Employee page, clock-in/out and concurrent attempts, schedule assignments and holidays, VL/SL submission/review/cancel, audit immutability, reports/filters/pagination and CSV/PDF exports. Measure the largest intended demo report and Livewire response. Confirm stderr logs do not expose credentials or password-reset links. Confirm .env/PHP source/dotfiles and storage uploads cannot be downloaded. Confirm the database TLS cipher.
6. Only after all those checks pass should you authorize a production deployment for this separate project. If durable workers or large reports are required and external services are unavailable, keep the Laravel backend on conventional PHP hosting and retain the portfolio on Vercel. Do not delete functionality to fit Hobby limits.
