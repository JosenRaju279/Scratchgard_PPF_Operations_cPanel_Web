# Registration, Identity Verification and Zone Policy

## 1. Applicator self-registration

The Super Admin controls the global identity rule from **Settings → Registration & Verification**:

- `email_only` — email is mandatory and must be verified.
- `mobile_only` — mobile is mandatory and must be verified.
- `email_or_mobile` — at least one is mandatory; every supplied contact is verified.
- `both` — both email and mobile are mandatory and both must be verified.

### Email normalization

Email is trimmed and stored in lowercase canonical form before uniqueness validation. This prevents duplicate logical accounts caused only by letter-case differences.

### Mobile normalization

Scratchgard stores mobile identity in three forms:

- `mobile_country_code` — e.g. `+91`
- `mobile_national_number` — exactly 10 digits
- `mobile` — canonical combined login value, e.g. `+919876543210`

The registration UI shows country code separately from the 10-digit mobile field. If a full mobile value is supplied through an API/admin input, the backend normalizes it to the same canonical form. Formatting differences such as spaces, dashes, `+91` or `91` therefore do not create separate users.

Super Admin can configure the default country code (default `+91`).

### Username

Every user has a unique lowercase username. If the applicant leaves username blank, Scratchgard creates a unique username from the person's name and adds a suffix when required.

The applicant/user can check username availability from the UI and later change their username from **My Profile**. Usernames are normalized to lowercase and may contain letters, digits, dot and underscore. Login accepts:

1. username
2. verified email
3. mobile number

Database uniqueness plus server-side normalization is the source of truth.

A registration remains `verification_pending` until all supplied required contacts are OTP-verified. It then becomes `pending` until KYC approval.

PIN code and full address are mandatory for Applicator registration. A PIN must resolve to at least one active Scratchgard zone when `registration_require_existing_zone` is enabled (recommended/default). If no active zone exists, registration is blocked.

## 2. One zone by default

Every operational user has exactly one `primary_zone_id` by default.

Global setting `allow_multiple_zones_default` is OFF by default. Super Admin can:

1. Keep one-zone behavior for everyone.
2. Allow multi-zone globally.
3. Keep global one-zone behavior and enable `allow_multiple_zones` only for a specific user.

Zone assignment history is recorded separately and is not silently overwritten.

## 3. Zone visibility during registration

`registration_zone_selection_mode` controls what the applicant sees after PIN resolution:

- `hidden_auto` — Scratchgard automatically assigns the first resolved active zone.
- `show_single` — all zones containing that exact PIN are shown and the applicant selects one.
- `show_multi` — multiple selections are shown only when multi-zone is allowed; otherwise the user can still choose only one primary zone.

If no zone contains the PIN, registration is blocked when the existing-zone requirement is enabled.

## 4. PIN-first zone architecture

Zone membership is not stored as free-text state/district/city names.

The admin may select **State → District → City/Locality → PIN** from the imported postal master for convenience, but when the mapping is saved Scratchgard expands that selection into the exact set of 6-digit PIN codes and stores those PIN memberships against the zone.

Example:

- Admin selects `Odisha` from the imported dataset for Zone A.
- Scratchgard materializes all selected Odisha PINs into Zone A.
- If the postal dataset later changes the state label from `Orissa` to `Odisha`, or someone spells it `Odisa`, the existing zone does not move because resolution uses the exact PIN memberships, not the state-name string.

Different zones may intentionally contain the same PIN. Registration will then return both zones. Same-zone duplicate PIN membership is prevented.

## 5. Profile updates

A signed-in user can update name, username, address, PIN and profile image.

A new email or mobile value is first stored as pending and requires OTP verification before replacing the existing verified value. Username changes do not require OTP, but they must pass normalization and real-time/server-side availability checks.

An authorised admin can update user profile, zone, verification flags and permissions from the backend.

## 6. KYC gate

Contact verification and KYC are separate gates. KYC approval is blocked while required contact verification is incomplete. Super Admin can always review KYC. Zonal Manager KYC approval is disabled by default and can be enabled globally or through per-user permissions.
