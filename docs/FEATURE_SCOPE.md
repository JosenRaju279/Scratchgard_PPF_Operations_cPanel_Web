# Scratchgard v2 — Included Feature Scope

## Users and authority

- Super Admin, Work Delegator Admin, Zonal Manager, Applicator, External Verifier and Finance.
- Role permissions plus per-user grant/deny overrides.
- Super Admin full authority.
- Destructive user/zone operations restricted; operational history preserved through disable/archive where history exists.

## Registration / identity / KYC

- Configurable registration identity: Email only, Mobile only, Email OR Mobile, or Email AND Mobile.
- Canonical lowercase email, country-code + exactly 10-digit national mobile normalization, unique database constraints and duplicate-collision detection.
- Unique username generation/availability checks; login by username, normalized email or normalized mobile.
- OTP verification for supplied required contacts.
- Mobile OTP provider can be a generic HTTP SMS gateway/plugin; log driver is setup/testing only.
- Applicator KYC submission and approval/reject/resubmission.
- KYC cannot be approved while supplied contact verification is incomplete.
- User can edit profile photo/address/PIN and request verified email/mobile changes.
- Admin can edit user details, verification state, zone policy and permissions.

## Postal master / zones

- India postal CSV import from offline official datasets.
- PIN, office, locality/city, district, state, division, region, circle, delivery and optional coordinates.
- Admin can select State / District / City / PIN from the imported postal master, but the saved operational boundary is materialized as exact 6-digit PIN memberships.
- State/district/city naming changes do not alter existing zones.
- `zone + PIN` is canonical and deduplicated inside the same zone.
- Mapping batches preserve provenance/history while avoiding duplicate PIN membership.
- Overlaps across different zones are allowed and returned to the UI with actual overlapping PIN counts rather than treated as fatal conflicts.
- Registration requires an existing active zone by default.
- Exactly one primary zone is the default; global or per-user multi-zone override is supported.
- Zone selection can be hidden/automatic, single-select, or multi-select when policy permits.

## Field operations

- VIN / vehicle master.
- Vendor/showroom masters and directions-ready showroom coordinates/address.
- Manual/API Work Order creation.
- Delegator → Zonal Manager routing.
- Zonal Manager Applicator assignment/reassignment with reason/history.
- Dashboard workload counts per Applicator (open/active/review/rework).
- Applicator Work Start panel: Work Order, VIN, registration, make/model/variant/color, showroom, address, directions, stage.
- Mobile-first PWA camera workflow.
- start/end GPS check-ins; no continuous tracking.
- server-side geofence calculation.
- mandatory before/after evidence templates and stage/vehicle-area metadata.
- original evidence + preview capability, retention date, protection flag and deletion scheduler.

## Quality / approval

- External Verifier/Showroom Manager assignment.
- External verifier can review/return/reject according to permission.
- Zonal Manager approval OFF by default; Super Admin can enable globally or per user.
- audit events preserve reviewer, role, reason, comments and state transition.

## Tickets / complaints / rework

- General/work-linked ticket system with participants, messages, assignment, priority and status.
- Complaint automatically creates a linked ticket.
- Complaint evidence protection.
- Rework assigned to same/different Applicator.
- `in_scope_rework` keeps original payment history intact.
- authorised scope change can create an `out_of_scope_new_work` linked child Work Order with independent pricing/payment.

## Payment

- Configurable mode:
  - `approval_status` (default current policy): approved work creates a Payment Done/paid ledger record;
  - `manual_finance`: approval creates eligible entry and authorised Finance/Super Admin records settlement.
- Paid original work is not silently reopened/unpaid because a later complaint/rework occurs.
- privileged payment correction via separate `payment.revert` permission, Super Admin-only by default.
- CSV export for finance/Tally mapping.

## Platform administration

- logo, favicon, brand name, footer, site/accent/text colours, font stack, base size and heading scale.
- SMTP settings/test.
- evidence retention, geofence, registration, OTP, zone and approval policies.
- reasons, pricing and evidence template configuration.
- Scratchgard Extension Framework 2.5: Super Admin-only plugin install/update/enable/disable/settings/uninstall, compatibility/dependencies, lifecycle, hooks/filters, plugin permissions, settings, migrations, routes/API routes, schedules, navigation, dashboard widgets, views/assets, health and activity audit.
- API Client manager with scopes, IP allowlist and expiry.
- audit trail.

## APIs

- first-party login/me/work-order endpoints.
- partner dealer/ERP create/read Work Order endpoints.
- dedicated partner Client ID/secret.
- abilities/scopes, IP allowlist, expiry and revoke.
- idempotency key protection against duplicate third-party orders.

## External/provider-specific configuration still required

- production SMS provider credentials/template/API shape;
- final Tally import field layout from Scratchgard finance;
- dealer-specific ERP mappings beyond the generic API contract;
- hosting-specific process limits/backups/monitoring.

## Before wide production

Perform end-to-end UAT on actual cPanel/VPS, Android/iOS browsers, actual SMTP/SMS provider, representative image sizes, weak connectivity, database backup/restore, queue/scheduler and security review. This package is a complete development/release foundation, not a substitute for environment-specific production testing.
