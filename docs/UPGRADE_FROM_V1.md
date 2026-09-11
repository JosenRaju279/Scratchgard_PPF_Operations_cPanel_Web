# Upgrade notes from Scratchgard package v1 to v2

v2 adds new migrations for identity verification, zone coverage, tickets, plugins/API clients, rework/payment controls and user-zone history.

For a development/test v1 database:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
```

Before upgrading a real installation:

1. take a full database backup;
2. back up `.env`, `storage/app`, plugin folders and evidence/object-store metadata;
3. stage the v2 code on a clone first;
4. run migrations and review existing user mobile formatting / zone mappings;
5. import postal master and create hierarchical coverage rules;
6. choose registration and payment tracking policies;
7. test one complete job and complaint/rework scenario;
8. then deploy to production.

Do not delete old Work Orders/payments to fit the new model. Preserve history and migrate operational mappings deliberately.

### Upgrade through v2.2.0
After replacing application files, run migrations again:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Migration v2.2 adds showroom-linked external verifier profile fields, OTP-audited review fields, public tracking tokens and email notification logs. Existing Work Orders are backfilled with random tracking tokens.

## Upgrade to 2.5.0 plugin framework

After replacing application files, run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Existing basic plugins remain supported when their `plugin.php` returns the old callable form. Review them in **Super Admin → Plugins** before enabling after an upgrade. Advanced plugins should adopt `App\Contracts\ScratchgardPlugin` and the 2.5 PluginContext API.

Plugin management is now role-hardcoded to Super Admin. Remove any operational expectation that `plugins.manage` can be granted to another role.


## Upgrade to 2.6.0 UI extension framework

After replacing files:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

No new core data migration is required solely for UI slots. Existing 2.5 plugins continue to load. Plugins can opt into `uiSlot`, `uiTab`, `formField`, `tableColumn` and `tableAction` without editing Scratchgard core files. Super Admin can open **Plugins → Plugin Builder Helper** for the current catalog and starter generator.
