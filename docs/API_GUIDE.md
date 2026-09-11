# Scratchgard API Guide

Base path:

```
https://YOUR-DOMAIN/api/v1
```

The web/PWA and any future native app should treat Laravel as the authoritative workflow/API layer.

# A. Staff / first-party API

## Login

`POST /api/v1/login`

```json
{
  "identifier": "username, applicator@example.com, or mobile",
  "password": "your-password",
  "device_name": "field-web-or-future-app"
}
```

The identifier is normalized using the same rules as the web login: lowercase email, country-code + 10-digit mobile, or lowercase username. Response contains a bearer token valid for the configured period (initial build: 30 days).

Use:

```
Authorization: Bearer TOKEN
```

Available initial routes:

- `GET /api/v1/me`
- `GET /api/v1/work-orders`
- `POST /api/v1/work-orders` (requires `work.create`)

The staff API is intentionally versioned so a future Flutter/native client can reuse the same users, jobs, VIN, evidence, approvals and database.

# B. Third-party Dealer / ERP API

Do not use staff credentials for dealer integrations. Create a dedicated API Client from **Super Admin → API Clients**.

The system displays the secret only once. Store it securely on the partner side.

## Authentication headers

```
X-Scratchgard-Client: sg_xxxxxxxxxx
X-Scratchgard-Secret: SECRET_SHOWN_ONCE
```

Optional controls on the API client:

- abilities / scopes;
- allowed source IP addresses;
- expiry date;
- enable / disable;
- immediate revoke/delete.

Supported abilities in the initial partner API:

- `work.create`
- `work.read`

## Create Work Order

`POST /api/v1/partner/work-orders`

Recommended request header:

```
Idempotency-Key: dealer-order-12345
```

If the same client retries the same idempotency key, Scratchgard returns the previously created Work Order instead of creating a duplicate.

Request:

```json
{
  "vin": "MAT12345678901234",
  "registration_number": "UP78AB1234",
  "make": "Tata",
  "model": "Safari",
  "variant": "Accomplished",
  "color": "White",
  "showroom_code": "TATA-KNP-01",
  "package_code": "FULL_PPF",
  "job_type": "NEW_INSTALLATION",
  "external_reference": "TATA-JOB-88921",
  "scheduled_at": "2026-09-15T10:30:00+05:30",
  "notes": "Dealer-created PPF request"
}
```

Example cURL:

```bash
curl -X POST "https://app.example.com/api/v1/partner/work-orders" \
  -H "Content-Type: application/json" \
  -H "X-Scratchgard-Client: sg_xxxxxxxxxx" \
  -H "X-Scratchgard-Secret: YOUR_SECRET" \
  -H "Idempotency-Key: TATA-JOB-88921" \
  -d '{"vin":"MAT12345678901234","make":"Tata","model":"Safari","showroom_code":"TATA-KNP-01","package_code":"FULL_PPF","external_reference":"TATA-JOB-88921"}'
```

Response includes:

- Scratchgard Work Order Number;
- external reference;
- status;
- VIN / registration;
- vehicle;
- showroom;
- zone;
- assigned Applicator when available;
- scheduled/approved/created timestamps.

## Read by Scratchgard Work Order Number

`GET /api/v1/partner/work-orders/SG-KNP-260911-00001`

Requires `work.read`.

## Read by partner external reference

`GET /api/v1/partner/external/TATA-JOB-88921`

Requires `work.read`.

A partner can read only Work Orders created by that same API Client.

# C. HTTP behavior

Typical responses:

- `200` successful read / idempotent replay
- `201` created
- `401` invalid client/token/secret
- `403` missing scope or permission
- `404` resource not found / not owned by API client
- `422` validation or workflow error
- `429` throttled

# D. API implementation rules

1. Do not let third parties choose arbitrary internal user IDs or bypass zone/showroom ownership.
2. A partner supplies a registered `showroom_code`; Scratchgard resolves vendor and zone from the showroom master.
3. VIN is normalized/used to identify the vehicle record.
4. Workflow state transitions still happen inside Laravel services; external clients do not directly set payment or approval status.
5. Add new endpoints under `/api/v1` and preserve backward compatibility; introduce `/api/v2` for breaking changes.
6. Log/revoke partner credentials immediately when compromised.

## Public tracking URL in API responses

New Work Orders receive a random public tracking token. Partner Work Order resource responses include `public_tracking_url` when tracking is enabled. This URL is intentionally read-only and privacy-filtered. It must not be treated as an authenticated API credential.

Example field:

```json
{
  "work_order_number": "SG-KNP-260911-00001",
  "status": "APPLICATOR_ASSIGNED",
  "public_tracking_url": "https://app.example.com/track/<opaque-token>"
}
```

The authenticated internal Work Order creation endpoint also returns the created Work Order plus `public_tracking_url`.
