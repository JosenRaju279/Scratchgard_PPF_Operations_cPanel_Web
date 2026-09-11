# Scratchgard PPF Operations — cPanel Web/PWA v2.8.0

## ERP cockpit UI (v2.8.0)

The authenticated product UI now uses the final Scratchgard ERP design system: a dark command sidebar, light operational workspace, global Work Order/VIN search, quick actions, responsive cards/tables and a role-scoped dashboard. Super Admin receives the full cockpit (KPIs, weekly activity, work status split, zone performance, pending actions, recent work, applicator workload, plugin health, email delivery and public-tracker/API health). Lower roles use the same shell but only see permitted data/modules. A role-scoped Evidence Library is available at `/evidence`.

## ERP home and role-specific login portals (v2.8.0)

The public application root (`/`) is now a responsive Scratchgard ERP gateway rather than an automatic dashboard redirect. It exposes role-specific login entry points while keeping one shared authentication/user database.

- Super Admin: `/super-admin/login`
- Work Delegator: `/delegator/login`
- Zonal Manager: `/zonal-manager/login`
- Applicator: `/applicator/login`
- Showroom / External Verifier: `/showroom/login`
- Finance: `/finance/login`
- Universal login: `/login`

Each role-specific portal rejects accounts belonging to another role. After successful authentication all users continue into the same scoped ERP dashboard and authorization system. The public `/` page presents ERP modules, workflow positioning and portal cards without exposing operational data.

Production-oriented Laravel cPanel package for Scratchgard PPF operations. **No native application is required for v1/v2 rollout**: Super Admin, Work Delegator, Zonal Manager, Applicator, External Verifier and Finance use the responsive web/PWA. The Laravel API remains versioned for a later native application without backend/database rewrite.

## Primary architecture

- Laravel 13 / PHP 8.3+
- Blade + mobile-first responsive PWA UI (single cPanel-friendly application)
- MySQL/MariaDB default for ordinary cPanel/phpMyAdmin; PostgreSQL supported when provided by host
- cPanel local private evidence storage or AWS S3
- database queue + cPanel cron fallback; persistent workers on VPS later
- SMTP email + configurable mobile OTP HTTP provider
- `/api/v1` first-party and partner API
- trusted PHP plugin extension layer

## v2 operational scope

- web installer + standalone preflight
- Super Admin bootstrap
- configurable unique identity policy: email / mobile / either / both
- canonical lowercase email + country-code/10-digit mobile normalization
- unique username generation, availability checks and username/email/mobile login
- OTP verification and KYC gate
- user self-profile editing with verified contact changes
- mandatory PIN + offline India postal dataset importer
- PIN-first zone engine: State/District/City selectors materialize exact PIN memberships
- state/district naming changes do not alter existing zones; intentional cross-zone PIN overlap remains visible
- one primary zone by default; global/per-user multi-zone override
- Super Admin zone CRUD/archive and user-level zone editing
- Vendor / showroom masters
- VIN / vehicle registry
- Work Orders, delegation, assignment and reassignment history
- Zonal Manager Applicator workload/progress dashboard
- mobile Applicator field panel with VIN, vehicle, showroom and directions
- camera-oriented evidence checklist + GPS/geofence check-ins
- External Verifier review + configurable Scratchgard approval power
- complaints → tickets → rework workflow
- in-scope vs out-of-scope rework conversion to a linked child Work Order
- status-based payment mode (Approved = Payment Done) or manual finance mode
- privileged audited payment correction (`payment.revert`)
- work chat + general ticket conversations
- branding/theme/footer/font settings
- evidence retention and complaint/legal-hold protection
- finance CSV foundation for Tally mapping
- API Client credentials for dealer/ERP integrations
- advanced Scratchgard Extension Framework 2.6 with Super Admin-only plugin management

## Start here

1. Read `docs/CPANEL_V2_SETUP.md`.
2. Create your DB/user in cPanel MySQL Database Wizard.
3. Point the domain/subdomain document root to this project's `public/` directory.
4. Run `composer install --no-dev --optimize-autoloader`.
5. Open `/preflight.php`.
6. Open `/install` and complete the wizard.
7. Import the India postal CSV before enabling public Applicator registration.

## Important deployment note

`vendor/` is intentionally not bundled. Install Composer dependencies on the server or build an audited release artifact in CI and upload it. Do not expose the project root, `.env`, storage, database dumps or plugin sources as the public document root.

## Documentation

- `docs/CPANEL_V2_SETUP.md`
- `docs/REGISTRATION_AND_ZONE_POLICY.md`
- `docs/LOCATION_DATA_SETUP.md`
- `docs/PAYMENT_REWORK_LOGIC.md`
- `docs/API_GUIDE.md`
- `docs/PLUGIN_DEVELOPER_GUIDE.md`
- `docs/AWS_S3_SETUP.md`
- `docs/FEATURE_SCOPE.md`

## v2.2: showroom verifier OTP, event email and public tracker

Third-party showroom/external verifier accounts are tied to a showroom and require a unique email. Review decisions can be protected by a Work-Order-specific email OTP. Core registration, KYC, work, assignment, status, complaint/rework, payment and ticket events email relevant participants through the centralized notification service. Every Work Order also gets an unguessable public read-only tracking URL that can be disabled or regenerated. See `docs/EMAIL_VERIFIER_AND_PUBLIC_TRACKING.md`.

## Scratchgard Extension Framework 2.6

Version 2.6 expands the advanced Super Admin-only plugin framework with WordPress-style UI slots, tabs, form fields, table columns/actions and the built-in Plugin Builder Helper. Trusted plugins can extend Scratchgard through documented actions/filters, plugin-specific permissions and settings, Laravel web/API routes, scheduled tasks, dashboard widgets, navigation, views/assets, migrations, lifecycle callbacks, dependencies and health checks.

Plugin install/update/enable/disable/settings/migrations/removal are **hard-restricted to the `super_admin` role** even if a lower user's permission record is modified. Plugin features may be exposed to other roles only through plugin-specific permissions defined by the plugin.

See `docs/PLUGIN_DEVELOPER_GUIDE.md` and `docs/plugin-example/`.
