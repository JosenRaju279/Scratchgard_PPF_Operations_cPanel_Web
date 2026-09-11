# Database and phpMyAdmin

## What the setup wizard expects
The installer needs the database credentials created in cPanel:
- Driver: MySQL/MariaDB or PostgreSQL
- Host
- Port
- Database
- Username
- Password

For MySQL/MariaDB, phpMyAdmin can be used after installation to inspect tables, run exports, or troubleshoot.

## Common cPanel naming
Many cPanel servers prefix databases/users:
- Database entered in installer: `cpuser_scratchgard`
- Username entered in installer: `cpuser_sguser`

Using only `scratchgard` may fail if cPanel shows the prefixed name.

## If DB connection fails
Check:
1. Database user is assigned to the database.
2. ALL PRIVILEGES were granted.
3. Host is correct (`localhost` vs `127.0.0.1`).
4. Password is exact.
5. Selected PHP has `pdo_mysql` or `pdo_pgsql`.
6. Remote DB host/firewall if DB is not local.

## Existing database
Use a dedicated empty database for first installation. The migration system will create Scratchgard tables. Do not point the installer at an unrelated production database with conflicting table names.
