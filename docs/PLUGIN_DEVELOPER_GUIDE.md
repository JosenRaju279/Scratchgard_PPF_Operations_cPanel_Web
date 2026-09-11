# Scratchgard Extension Framework 2.6 — Plugin Developer Guide

## 1. Security and ownership

Scratchgard plugins are **trusted server-side extensions**. A plugin can execute PHP inside the Laravel application, register routes/services/listeners, interact with the database and integrate with third-party services. Install only reviewed code.

**Plugin management is hard-restricted to `super_admin`.** It does not rely on a grantable `plugins.manage` permission. Lower roles can only use a plugin feature when the plugin explicitly exposes that feature and protects it with a plugin-specific permission/role rule.

## 2. Required ZIP structure

```text
plugin.json
plugin.php
[database/migrations/*.php]
[resources/views/*.blade.php]
[public/*]
```

New installs are disabled. Super Admin reviews and enables them.

## 3. Advanced manifest

```json
{
  "name": "Dealer Sync",
  "slug": "dealer-sync",
  "version": "1.4.0",
  "description": "Syncs dealer work orders and exposes a dashboard widget.",
  "author": "Scratchgard Integrations",
  "homepage": "https://example.invalid",
  "requires": {
    "scratchgard": ">=2.6.0",
    "php": ">=8.3",
    "plugins": {
      "another-plugin": ">=1.0.0"
    }
  },
  "permissions": [
    {"key":"sync.run","name":"Run dealer sync"},
    {"key":"reports.view","name":"View dealer sync report"}
  ],
  "settings": [
    {"key":"endpoint","label":"Dealer API URL","type":"text","required":true},
    {"key":"api_secret","label":"API secret","type":"password","secret":true},
    {"key":"enabled","label":"Enable automatic sync","type":"boolean","default":true},
    {"key":"mode","label":"Mode","type":"select","default":"live","options":{"sandbox":"Sandbox","live":"Live"}}
  ]
}
```

Supported compatibility operators include `>=`, `<=`, `>`, `<`, `=`, `^`, `~` and comma/space-separated combinations.

## 4. Recommended plugin.php

`plugin.php` can return a callable for backwards compatibility, but 2.5 recommends an object implementing `App\Contracts\ScratchgardPlugin`.

```php
<?php
use App\Contracts\ScratchgardPlugin;
use App\Plugins\PluginContext;

return new class implements ScratchgardPlugin {
    public function boot(PluginContext $p): void {
        $viewPermission=$p->permission('reports.view','View example report');

        $p->on('work.created', function ($work, $actor) {
            // react to a core work order event
        });

        $p->filter('notification.recipients', function ($users, $eventType) {
            return $users;
        });

        $p->webRoutes(function () use ($viewPermission) {
            \Illuminate\Support\Facades\Route::get('/report', fn()=>'Plugin report')
                ->middleware('permission:'.$viewPermission)
                ->name('report');
        });

        $p->navigation('report','Dealer report',url('/extensions/'.$p->slug().'/report'),$viewPermission);
        $p->dashboardWidget('summary','Dealer sync',fn($user)=>'<p>Extension widget</p>',$viewPermission);
        $p->schedule(fn()=>null,'*/15 * * * *','dealer-sync');
        $p->views();
    }
    public function activate(PluginContext $p): void {}
    public function deactivate(PluginContext $p): void {}
    public function uninstall(PluginContext $p, bool $purgeData=false): void {}
    public function update(PluginContext $p, ?string $fromVersion, string $toVersion): void {}
    public function health(PluginContext $p): array { return ['ok'=>true]; }
};
```

## 5. Extension API available to plugins

`PluginContext` provides:

- `on($hook, $callback, $priority)` — subscribe to Scratchgard actions.
- `filter($hook, $callback, $priority)` — alter approved values before core processing continues.
- `setting($key, $default)` — read plugin setting.
- `permission($key, $name)` — register `plugin.<slug>.<key>` capability.
- `webRoutes($callback)` — routes under `/extensions/<slug>`.
- `apiRoutes($callback)` — routes under `/api/extensions/<slug>`.
- `navigation(...)` — add role/permission-aware navigation.
- `dashboardWidget(...)` — add permission-aware dashboard card.
- `schedule(...)` — register cron/scheduler work with Scratchgard's Laravel scheduler.
- `views()` — register `resources/views` namespace as `plugin-<slug>::...`.
- `asset($path)` — URL for files published from plugin `public/` directory.
- `app()` — Laravel container for advanced/native Laravel extension work.

Because plugins are trusted PHP, they may additionally use Laravel's Events, container bindings, HTTP client, jobs, notifications, database models, commands and other framework facilities. Prefer the Scratchgard extension API for core business integrations so updates remain easier to maintain.

## 6. Core hooks provided by Scratchgard 2.5

### Work
- `work.create.data` **filter** — alter validated Work Order create data.
- `work.create.before`
- `work.created`
- `work.transition.target` **filter**
- `work.transition.before`
- `work.event` — receives every WorkEvent.
- `work.event.<event.name>` — e.g. `work.event.work.approved`.
- `work.status.changed`
- `work.approval.before`

### Payments
- `payment.approval.defaults` **filter**
- `payment.eligible`
- `payment.event`
- `payment.event.<event.name>`

### Evidence / location
- `evidence.meta` **filter** — alter validated evidence metadata before storage.
- `evidence.disk` **filter** — select an approved Laravel filesystem disk.
- `evidence.retention_days` **filter** — change retention duration for a particular evidence item.
- `evidence.before_store`
- `evidence.stored`
- `location.checkin`

### Users / KYC
- `user.registration.data` **filter**
- `user.registered`
- `user.identity.verified`
- `kyc.reviewed`

### Tickets
- `ticket.create.data` **filter**
- `ticket.created`
- `ticket.event`
- `ticket.event.<event.name>`

### Notifications
- `notification.recipients` **filter**
- `notification.message` **filter** — `[subject, body]` pair.
- `notification.before_send`
- `notification.sent`
- `notification.failed`

### Plugin system
- `plugin.settings.updated`

New core hooks should be added rather than directly modifying Scratchgard core files when a recurring extension point is required.

## 7. Settings

Settings are defined in `plugin.json`. The Super Admin gets a generated settings screen. Fields marked `secret:true` are encrypted before database storage and existing secret values are never displayed back in the form.

Common field types: `text`, `password`, `textarea`, `number`, `boolean`, `select`.

## 8. Plugin migrations

Put standard Laravel migration files in:

```text
database/migrations/
```

They run on enable/update or when Super Admin selects **Run plugin migrations**. Prefix migration filenames/table names with the plugin slug to minimize collisions. Plugin migrations should create plugin-owned tables and must not destructively modify core Scratchgard tables.

## 9. Public assets and views

`public/` is published to `public/plugins/<slug>/` on activation. `resources/views` is available under namespace `plugin-<slug>`.

## 10. Update lifecycle

Uploading a ZIP with an already-installed slug is an update. Scratchgard:
1. validates compatibility;
2. backs up the old plugin directory under `storage/app/plugin-backups/`;
3. replaces plugin files;
4. preserves plugin settings;
5. runs migrations/update lifecycle if the plugin was enabled;
6. disables the plugin if update tasks fail, rather than leaving a silently broken enabled extension.

## 11. Dependencies

A plugin can require other enabled plugins via `requires.plugins`. Scratchgard blocks activation when a dependency is missing, disabled or outside its version constraint.

## 12. Uninstall / data safety

Normal removal preserves plugin-owned business data where possible. Super Admin may explicitly enable **purge plugin data**, in which case the plugin `uninstall(..., true)` callback can delete its own data. Never delete core Work Orders, audit history, payments or evidence as an uninstall side effect.

## 13. Health and auditing

The Super Admin plugin detail page shows compatibility, dependencies, runtime errors, checksum, lifecycle state and activity history. Plugins may provide their own `health()` diagnostics.

## 14. Scheduler on cPanel

Plugin schedules use the same Laravel scheduler. The cPanel cron must continue running:

```bash
* * * * * cd /home/USER/scratchgard && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

No plugin should create a second independent cPanel cron unless absolutely required.

## 15. UI Extension Framework (Scratchgard 2.6+)

Scratchgard exposes stable WordPress-style UI extension points so a plugin can add cards, buttons, tabs, fields, table columns and row actions to existing core screens without editing core Blade files.

### UI slots

```php
$p->uiSlot(
    'work.show.after_vehicle',
    'dealer-status-card',
    function(array $context, $user) {
        $work=$context['workOrder'];
        return view('plugin-'.$p->slug().'::dealer-status',compact('work'))->render();
    },
    permission: $permission,
    roles: ['zonal_manager','super_admin'],
    when: fn(array $context,$user) => $context['workOrder']->status==='APPROVED',
    priority: 20
);
```

The current stable slot catalog is visible inside **Super Admin → Plugins → Plugin Builder Helper** and in `config/plugin_ui.php`.

### Core screen tabs

```php
$p->uiTab('work.show','dealer-tab','Dealer Sync',fn(array $context)=>view('plugin-'.$p->slug().'::sync',$context)->render(),$permission);
```

Supported tab screens currently include `work.show`, `profile.show` and `ticket.show`.

### Form fields

```php
$p->formField('user.profile','certification_number',[
    'label'=>'PPF Certification Number',
    'type'=>'text',
    'required'=>true,
    'help'=>'Issued by the certification authority.'
],$permission);
```

Supported field types include `text`, `email`, `number`, `date`, `datetime-local`, `url`, `tel`, `textarea`, `select` and `checkbox`. Plugin fields are submitted under `plugin_fields[plugin-slug][field-key]`. The plugin remains responsible for validation/business storage, normally via the corresponding Scratchgard hook or a plugin-owned endpoint/table.

Useful persistence hooks include:

- `work.created.fields($work, $pluginFields, $actor)`
- `user.admin.created($user, $pluginFields, $actor)`
- `user.admin.updated($user, $pluginFields, $actor)`
- `user.profile.updated($user, $pluginFields, $request)`
- `ticket.reply.created($ticket, $message, $pluginFields, $actor)`

### Table columns and row actions

```php
$p->tableColumn('work_orders','dealer_sync','Dealer Sync',fn($work)=>'<span class="badge">Synced</span>',$permission);

$p->tableAction(
    'work_orders',
    'resync',
    'Re-sync',
    fn($work)=>route('ext.dealer-sync.resync',$work),
    $runPermission,
    method:'POST'
);
```

Supported tables currently include `work_orders`, `users` and `payments`. POST/PUT/PATCH/DELETE actions are rendered as CSRF-protected forms by the core UI.

### Context-aware visibility

UI extensions may be constrained simultaneously by:

- plugin-specific permission;
- Scratchgard role;
- arbitrary `when($context,$user)` callback;
- Work Order state;
- zone/user relationship;
- vendor/showroom identity;
- any plugin-owned policy.

Hiding a UI component is **not** authorization. The plugin route/controller must enforce the same permission/scope server-side.

### Layout hooks

Trusted plugins can also inject into controlled layout slots such as `layout.head`, `layout.after_topbar`, `layout.before_content`, `layout.after_content` and `layout.before_footer`. Use these sparingly. Prefer plugin `public/` assets and `asset()` instead of large inline scripts.

## 16. Super Admin Plugin Builder Helper

Scratchgard includes **Super Admin → Plugins → Plugin Builder Helper**. It provides:

- current UI slot catalog;
- tab/form/table extension catalog;
- copy-ready hook examples;
- permission/context examples;
- route/API/scheduler guidance;
- plugin package anatomy;
- a starter-plugin ZIP generator.

The generated starter ZIP demonstrates a Work Order UI card, Work Order tab, Work Order table column/action, profile field, dashboard widget, navigation item, web route, business hook and scheduled task. It installs disabled and still requires Super Admin review/enable.
