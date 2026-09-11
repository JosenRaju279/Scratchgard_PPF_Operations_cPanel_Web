# Scratchgard ERP Login URLs

Replace `https://app.example.com` with the actual cPanel domain/subdomain.

| Portal | URL | Allowed role |
|---|---|---|
| ERP Home | `https://app.example.com/` | Public gateway |
| Universal Login | `https://app.example.com/login` | Any active user |
| Super Admin | `https://app.example.com/super-admin/login` | Super Admin only |
| Work Delegator | `https://app.example.com/delegator/login` | Work Delegator only |
| Zonal Manager | `https://app.example.com/zonal-manager/login` | Zonal Manager only |
| Applicator | `https://app.example.com/applicator/login` | Applicator only |
| Showroom / External Verifier | `https://app.example.com/showroom/login` | External Verifier only |
| Finance | `https://app.example.com/finance/login` | Finance only |
| Applicator Registration | `https://app.example.com/applicator/register` | Public registration request |
| Work Tracker | `https://app.example.com/track/{secure-token}` | Anyone possessing the exact secure link |

## Security behavior

All role portals use the same authentication database, password hashes, account status, identity normalization and session system. A role URL is not merely visual: if an Applicator attempts to use `/super-admin/login`, Scratchgard rejects the role mismatch even when the credentials themselves are valid.

The universal `/login` remains available for support/operational convenience. Backend permissions and data scopes continue to control what each authenticated account can actually access.
