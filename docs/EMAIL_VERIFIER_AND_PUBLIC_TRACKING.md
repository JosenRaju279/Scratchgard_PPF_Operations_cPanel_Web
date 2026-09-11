# External Verifier OTP, Email Notifications and Public Work Tracking

## Third-party showroom manager / external verifier

Create the account from **Users** with role **External Verifier**. A unique email and showroom are required. The optional designation can be Showroom Manager, Quality Manager, Service Manager, Dealer Representative, etc.

By default `approval_otp_required` is enabled. When the assigned work is ready for review, the verifier opens the Work Order, selects **Send / resend review OTP**, and receives a six-digit code at the email stored in that account. The OTP is tied to that Work Order and expires using the global OTP expiry policy. The verifier must enter the OTP before Approve / Return / Quality Hold / Reject is accepted. The review audit record stores the verification method and verification record ID.

A verifier linked to a specific showroom cannot be assigned to a different showroom. A temporary/general verifier can be implemented by creating an account for the required showroom and disabling it when no longer required.

## Transactional email notifications

When `email_notifications_enabled` is ON, the application emails relevant users for important events. Work events include the directly assigned Zonal Manager, Applicator and External Verifier, plus active Work Delegators and Super Admins when those oversight notification settings are enabled.

Examples include:
- Applicator registration and identity verification
- KYC decision
- Work creation
- Delegation
- Applicator assignment/reassignment
- External verifier assignment
- Work start/submission/status decisions
- Approval / return / rejection / quality hold
- Complaint / rework changes
- Payment changes
- Ticket creation, status changes and replies

All email attempts are written to `notification_logs`. Super Admin can see recent delivery attempts under **System Settings**. SMTP failure never rolls back the underlying operational event.

## Public work tracker

Every Work Order receives a random 64-character tracking token and a public read-only URL:

`https://your-domain.example/track/<random-token>`

Anyone who has the exact URL can view the high-level progress. There is no sequential Work Order ID in the public URL.

Public output intentionally excludes:
- full VIN (masked in tracker)
- evidence/photos
- KYC information
- email/mobile/contact details
- internal chat or tickets
- approval OTPs
- pricing/payment information
- internal complaint notes

Authorised Scratchgard staff can disable public tracking or regenerate the token. Regeneration immediately invalidates the previous URL.

## cPanel mail requirement

Configure SMTP in **System Settings** after installation. Use **Send test email** before live onboarding. The application stores delivery failures in the notification log, which is especially useful on cPanel hosts that block or rate-limit outbound SMTP.
