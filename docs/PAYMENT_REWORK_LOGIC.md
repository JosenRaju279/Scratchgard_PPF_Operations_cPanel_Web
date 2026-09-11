# Payment, Complaint, Rework and Scope Logic

## Core rule

A historical payment is a ledger event. A later complaint or rework must not silently rewrite a payment that was already paid.

## Approval → payment

Scratchgard v2 supports two Super Admin-configurable tracking modes.

- `approval_status` (default current policy): when work reaches valid approval, the single payment ledger row is created as `paid` / Payment Done using the locked job price and approval timestamp. This is status-based operational payment tracking, not a bank reconciliation feed.
- `manual_finance`: approval creates one `eligible` row; Finance/Super Admin later records the actual settlement.

Repeated approval calls do not create duplicate payment rows.

Possible states include:

- `eligible`
- `held`
- `paid`
- `reversed`

## Complaint after approval

When a complaint opens:

- if payment is still `eligible`, it may be changed to `held` pending resolution;
- if payment is already `paid`, it stays `paid`;
- the complaint receives a ticket;
- evidence is protected from normal retention deletion while the complaint is active.

## In-scope rework

Default rework classification is `in_scope_rework` with `no_new_payment`.

This means the corrective work is considered part of the original obligation. Original approval/payment history remains intact. Rework has its own assignment, evidence, check-ins, messages and review result.

## Reassigning rework

An authorised Zonal Manager/Super Admin can assign the rework to the same or a different Applicator. Previous assignment/history remains visible.

## Reclassifying to out-of-scope / new paid work

Sometimes a job was initially marked as rework but later Scratchgard determines it is outside the original scope and should be a separate paid job.

A user with `work.change_rework_scope` can change the classification to `out_of_scope_new_work`. The system then creates a **linked child Work Order**:

- same VIN/vehicle;
- linked to the original Work Order and originating rework;
- same showroom/zone unless changed later through permitted workflow;
- same or newly selected Applicator;
- its own pricing/payment lifecycle.

The original Work Order remains unchanged.

## Privileged payment status correction

`payment.revert` is a distinct capability and is **Super Admin only by default**. Super Admin can grant it to a specific authorised user if required.

A privileged correction requires:

- target status;
- configured reason;
- mandatory audit note;
- actor and timestamp.

The action creates an audit/Work Event. It is not a silent database edit.
