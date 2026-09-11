# Scratchgard v2 — cPanel Installation & Setup

This release is web/PWA-first. No native app is required for v1. The Applicator uses the same responsive web application on the phone. A future native app can reuse the versioned Laravel API.

## Recommended cPanel environment

- PHP 8.3+ (8.4 is fine)
- MySQL/MariaDB + phpMyAdmin for the easiest ordinary cPanel setup
- or PostgreSQL when your hosting account provides it
- PDO driver for the selected DB
- cURL, mbstring, OpenSSL, Fileinfo
- PHP Zip if the plugin manager will be used
- Composer 2
- HTTPS certificate
- cron access

A cPanel VPS is preferred for production. Shared cPanel can run the pilot when resource/process limits are adequate.

## 1. Upload and document root

Extract the package outside public_html when possible, e.g.:

```
/home/CPANELUSER/scratchgard
```

Point `app.yourdomain.com` document root to:

```
/home/CPANELUSER/scratchgard/public
```

If the host forces `public_html`, see `CPANEL_PUBLIC_HTML_FALLBACK.md`.

## 2. Database through cPanel/phpMyAdmin

In **cPanel → MySQL Database Wizard**:

1. create database, e.g. `cpuser_scratchgard`;
2. create database user;
3. assign the user to the database with ALL PRIVILEGES;
4. note database name, username and password.

phpMyAdmin is for viewing/managing the DB; the Laravel installer connects with DB host/name/user/password. You normally do **not** manually create the application tables.

## 3. Composer

Terminal:

```bash
cd /home/CPANELUSER/scratchgard
composer install --no-dev --optimize-autoloader
```

If cPanel has a Composer interface, run/install dependencies there against this directory.

## 4. Preflight

Open:

```
https://app.yourdomain.com/preflight.php
```

Resolve any red checks. The plugin feature additionally needs PHP Zip.

## 5. Web setup wizard

Open:

```
https://app.yourdomain.com/install
```

The wizard:

- tests DB connection;
- writes `.env`;
- creates APP_KEY;
- optionally tests S3;
- runs migrations;
- seeds roles/permissions/reasons/evidence defaults;
- creates the first verified Super Admin;
- writes the install lock.

For normal cPanel choose **MySQL / MariaDB**, host usually `localhost` or `127.0.0.1`, port `3306` unless your provider says otherwise.

## 6. Evidence storage

### Option A — cPanel local private storage

Choose Local during installation. Evidence is kept under Laravel storage and should be served only through authorised application routes.

### Option B — AWS S3

Choose S3 and enter key, secret, region and private bucket. The wizard performs a write/delete check. See `AWS_S3_SETUP.md`.

Keep the app storage-abstracted so Local → S3 migration does not require workflow rewrite.

## 7. Scheduler and queue

Laravel scheduler:

```cron
* * * * * cd /home/CPANELUSER/scratchgard && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

For shared cPanel where persistent workers are unavailable:

```cron
* * * * * cd /home/CPANELUSER/scratchgard && /usr/local/bin/php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

On a VPS use a persistent queue worker/process manager instead.

Use `which php` in Terminal if your PHP binary path differs.

## 8. First operational setup order

1. Settings → branding/theme/footer.
2. SMTP and test email.
3. Registration identity policy and OTP mode.
4. Import India postal CSV.
5. Create Scratchgard zones and coverage rules.
6. Create Zonal Managers and their primary zones.
7. Create vendors/showrooms and showroom coordinates.
8. Configure pricing, evidence templates and reasons.
9. Configure KYC permissions.
10. Create external verifier users where needed.
11. Test Applicator registration with a real mapped PIN.
12. Create a test Work Order and run complete field flow.
13. Create API Client only when a dealer/ERP integration is ready.

## 9. Production hardening

After confirming install:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Keep `APP_DEBUG=false`, use HTTPS, private evidence storage, strong Super Admin credentials and provider backups. Do not expose `.env`, `storage`, application source, database backups or plugin files as public web roots.


## Plugin framework 2.5

Plugin management is hard-restricted to Super Admin. Ensure PHP `zip` is enabled. Upload trusted plugin ZIPs from **Super Admin → Plugins**. New plugins install disabled. Plugin migrations run through Laravel; scheduled plugin tasks use the existing `schedule:run` cPanel cron. Do not expose `/plugins` source files through the public document root. See `docs/PLUGIN_DEVELOPER_GUIDE.md`.


## ERP entry URLs after installation (v2.7)

After the installer completes, the main root URL is the Scratchgard ERP gateway. Role-specific login URLs are:

- `/super-admin/login` — Super Admin only
- `/delegator/login` — Work Delegator only
- `/zonal-manager/login` — Zonal Manager only
- `/applicator/login` — Applicator only
- `/showroom/login` — Showroom / External Verifier only
- `/finance/login` — Finance only
- `/login` — universal login for any active account

See `docs/LOGIN_URLS.md` for details.
