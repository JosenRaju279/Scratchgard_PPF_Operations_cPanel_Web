# Scratchgard cPanel Web/PWA — Release 2.8.0 / Changelog

## 2.8.0 — ERP Cockpit UI & Responsive Command Center
- Rebuilt authenticated UI into a responsive dark-sidebar/light-canvas ERP shell.
- Added Super Admin cockpit with KPI cards, weekly activity chart, status split, pending actions, zone performance, recent work, workload and system-health widgets.
- Added global Work Order/VIN/registration/showroom search from the top bar.
- Added role-filtered Applicator and External Verifier user views.
- Added role-scoped Evidence Library.
- Preserved plugin UI slots, tabs, form fields, table extensions and Super Admin-only Plugin Manager.
- Added mobile sidebar and responsive dashboard layouts for PWA use.


## 2.7.0 — ERP Gateway & Role-Specific Login Portals

- Added polished responsive ERP landing page at `/`.
- Added locked role portals for Super Admin, Work Delegator, Zonal Manager, Applicator, Showroom/External Verifier and Finance.
- Retained universal `/login` for operational convenience.
- Role-specific login rejects users from the wrong role instead of treating the URL as cosmetic branding.
- Logout returns users to their own role portal.
- Guest brand link now returns to the ERP home page.
- Applicator portal continues to expose self-registration.

## 2.6.0 — WordPress-style UI Extension Framework
- Added stable UI slots across dashboard, Work Orders, user/profile, payments, tickets and global layout.
- Added plugin tabs on Work Order, profile and ticket screens.
- Added plugin form fields for user creation, self profile, Work Order creation and ticket replies.
- Added plugin table columns/actions for Work Orders, users and payments; non-GET actions use CSRF-protected forms.
- Added role + permission + context-callback visibility rules for plugin UI.
- Added plugin field lifecycle hooks so extensions can persist plugin-owned custom field data without altering core tables.
- Added Super Admin-only Plugin Builder Helper with live catalog, examples and downloadable starter ZIP generator.
- Updated example extension to demonstrate core-screen injection.
- Plugin Manager remains hard Super Admin-only. Plugin feature permissions can still be delegated separately.

## Identity and registration changes

- Country-code-aware mobile input with exactly 10 national digits.
- Canonical mobile storage (`country_code`, 10-digit national number, combined login value).
- Lowercase/trimmed email normalization before uniqueness checks.
- Unique usernames for every user.
- Automatic username generation from name when left blank.
- Username availability checking and self-service username changes.
- Login by username, normalized email or normalized mobile.
- Existing-user migration detects normalization collisions instead of silently merging accounts.

## Zone mapper changes

- Runtime zone resolution is now PIN-first.
- State/District/City are admin selection filters only.
- Zone builder materializes exact six-digit PIN memberships.
- State spelling/renaming changes do not alter existing zones.
- `zone + PIN` canonical membership is unique.
- Same-zone duplicate mappings are deduplicated/rejected.
- Cross-zone PIN overlap remains explicitly supported.
- Mapping-batch provenance allows safe removal without deleting PIN membership still referenced by another batch.
- Overlap preview reports actual PIN intersection counts.

## Existing v2 scope retained

- configurable email/mobile/either/both identity policy
- OTP verification + KYC gate
- one primary zone by default with Super Admin exceptions
- tickets, complaints, rework, payment controls
- VIN/work orders/evidence/GPS
- responsive PWA
- cPanel installer
- plugin/API-client architecture

## v2.2.0
- Added showroom-linked External Verifier profile fields and designation.
- Added email-OTP confirmation for External Verifier review decisions.
- Added centralized operational email notifications and notification delivery logs.
- Added public read-only Work Order tracking URLs with random tokens, disable/regenerate controls and privacy-safe output.
- Added ticket reply/status emails and complaint/rework/payment event emails.
- Added public-tracker links to Work Order emails.

# 2.5.0 — Advanced Extension Framework

- Replaced the basic plugin loader with Scratchgard Extension Framework 2.5.
- Plugin management is enforced by dedicated Super Admin middleware and controller checks; it cannot be delegated through normal permission overrides.
- Added plugin semantic-version compatibility checks for Scratchgard/PHP and dependency checks for other plugins.
- Added plugin lifecycle: install/update, activate, boot, deactivate, uninstall and health.
- Added trusted actions/filters for Work Orders, payments, users/KYC, tickets and notifications.
- Added plugin-specific permission registration, generated settings UI with encrypted secrets, migrations, web/API routes, navigation, dashboard widgets, schedules, views and public assets.
- Added update backups under `storage/app/plugin-backups/` and fail-closed behavior when enabled-plugin update tasks fail.
- Added plugin activity audit log and runtime error visibility.
- Added safe data-preserving uninstall by default and explicit Super Admin purge-data option.
- Added example extension source under `docs/plugin-example/`.
