# AWS S3 Setup for Scratchgard on cPanel

The Laravel server may remain on cPanel. S3 is separate object storage reached over HTTPS.

## Recommended layout
- Laravel: cPanel/VPS
- Database: cPanel MySQL/MariaDB or PostgreSQL
- Evidence: AWS S3
- Browser/PWA: same Scratchgard web application

## Create bucket
1. AWS Console -> S3 -> Create bucket.
2. Example name: `scratchgard-evidence-production`.
3. Region: choose the required operating region; for India, `ap-south-1` is a common choice.
4. Keep **Block Public Access** enabled.
5. Enable bucket versioning only if the retention/business policy requires it; versioning increases retained storage.

## Create restricted IAM credentials
Create a dedicated IAM identity for Scratchgard evidence access. Do not use the AWS root access key.

Scope permissions to the evidence bucket. The application needs object read/write/delete/list actions appropriate for its evidence workflow. Avoid account-wide S3 administration permissions.

## Installer
Choose **AWS S3** on `/install` and enter:
- AWS Access Key ID
- AWS Secret Access Key
- Region
- Bucket

The installer runs a small write/delete test before completing.

## Application behavior
The storage driver is abstracted. Evidence records store the disk name and object path, so deployment can use:
- cPanel private local storage initially, or
- S3, or
- later an S3-compatible provider.

## Retention
Scratchgard has an application-level retention scheduler:
- default standard evidence retention: 30 days,
- configurable by Super Admin,
- complaint/rework/legal-hold evidence is protected,
- protected evidence is not deleted by normal retention cleanup.

You may also configure a bucket lifecycle policy as a second storage-cost control. Do not create an S3 lifecycle rule that deletes protected evidence sooner than the application's complaint/legal-hold policy.

## CORS
This release uploads evidence through Laravel. S3 CORS is therefore not required for normal evidence capture.

If a future release switches to browser-direct signed uploads, add CORS only for the exact Scratchgard application origins and required methods/headers.

## Secrets
AWS credentials are stored in `.env`, not in browser JavaScript. Never expose AWS secret keys in PWA source code.
