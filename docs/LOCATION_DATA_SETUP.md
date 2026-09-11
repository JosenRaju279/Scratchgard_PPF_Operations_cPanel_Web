# India Postal Dataset and PIN-First Scratchgard Zone Mapping

Scratchgard should not depend on a live free location API for core registration/routing. Import an offline India postal dataset into the local `pincodes` table and refresh it periodically.

## Recommended source

Use an official India Post / Government of India postal directory CSV where available. The importer accepts common headers such as:

`pincode`, `officename`, `office_name`, `statename`, `state`, `district`, `districtname`, `taluk`, `city`, `locality`, `divisionname`, `regionname`, `circlename`, `delivery`, `latitude`, `longitude`.

Review the source and header row before import. A small example file may be kept under `docs/examples/` for format testing.

## Import flow

1. Sign in as Super Admin.
2. Open **Zones & Masters**.
3. Upload the postal CSV.
4. Scratchgard creates/updates postal rows by PIN + post-office identity where possible.
5. Test multiple known PINs before creating zone mappings.

## Why zone resolution is PIN-based

Administrative names are not reliable long-term identifiers. They can be renamed, transliterated differently, or typed differently: for example `Orissa`, `Odisha`, or an incorrect `Odisa`.

Therefore Scratchgard does **not** use typed state/district/city names to resolve a user's zone at runtime.

Instead:

1. Admin selects geography only from the imported postal dataset.
2. Scratchgard finds all exact 6-digit PIN codes represented by that selection.
3. Those PINs are materialized into canonical `zone + pincode` memberships.
4. Runtime registration/job routing simply asks: **which active zones contain this PIN?**

This keeps existing zone boundaries stable even when postal labels later change.

## Building a zone

The UI can build a zone from:

- an entire State
- a District inside a selected State
- a City/Locality inside a selected District
- one exact PIN

These are selection tools only. The saved zone membership is still exact PIN codes.

## Duplicate and overlap rules

### Same zone

`zone + PIN` is unique. If State mapping already placed PIN `751001` into Zone A, adding a child District/City that also contains `751001` does not create a duplicate canonical membership.

An exact duplicate mapping selection is rejected. A selection that adds no new PIN membership is also rejected as a no-op.

### Different zones

The same PIN may intentionally belong to Zone A and Zone B. This is an overlap, not an error.

Before saving a mapping, the UI previews:

- number of PINs represented by the selection
- zones already containing any of those PINs
- count of overlapping PINs per zone

At registration, if the applicant's PIN belongs to multiple zones, Scratchgard returns all matching zones. Default account assignment remains one primary zone unless Super Admin enables user selection/multi-zone behavior.

## Mapping batches and history

Each admin selection creates a mapping batch for audit/provenance. Canonical PIN memberships are deduplicated separately. This means:

- State mapping + later District mapping in the same zone does not duplicate PIN membership.
- Removing one mapping batch removes only its source relationship.
- A PIN remains in the zone when another mapping batch still references it.
- Cross-zone overlap is unaffected.

## Refreshing the postal master

Refreshing the postal dataset does not silently rewrite existing zone memberships. Existing zones continue to use the exact PINs materialized when mappings were created. Admin can intentionally add/remove mapping batches when operational boundaries change.
