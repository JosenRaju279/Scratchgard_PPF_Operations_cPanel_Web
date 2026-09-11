@extends('layouts.app')
@section('title','Plugin Builder Helper')
@section('content')
<div class="page-head"><div><p class="eyebrow">Super Admin · Extension Framework</p><h1>Plugin Builder Helper</h1><p class="muted">Build WordPress-style Scratchgard extensions without modifying core files. Plugins can inject UI into approved slots, add tabs/fields/table columns/actions, register permissions, routes, APIs, migrations, scheduled jobs and business hooks.</p></div><div class="page-actions"><a class="btn secondary" href="{{ route('plugins.index') }}">← Plugins</a></div></div>

<div class="alert warning"><strong>Trusted-code model:</strong> installed plugins execute server-side PHP inside Scratchgard. Only Super Admin can install, update, enable, disable, configure or remove plugins. Review source before enabling.</div>

<div class="grid">
<div class="col-5 card"><h2>Generate starter plugin ZIP</h2><p class="muted">Creates a ready-to-edit extension with manifest, lifecycle class, Work Order UI slot, tab, table column/action, custom profile field, dashboard widget, navigation and route examples.</p><form method="post" action="{{ route('plugins.builder.starter') }}">@csrf
<div class="field"><label>Plugin name *</label><input name="name" required placeholder="Dealer CRM Connector"></div>
<div class="field"><label>Slug *</label><input name="slug" required pattern="[a-z0-9][a-z0-9-]*" placeholder="dealer-crm-connector"><small>Lowercase letters, numbers and hyphens.</small></div>
<div class="field"><label>Description</label><textarea name="description" placeholder="What this extension does"></textarea></div>
<div class="field"><label>Author</label><input name="author" placeholder="Your company / developer"></div>
<button class="btn wide">Download starter ZIP</button></form></div>
<div class="col-7 card"><h2>Plugin package anatomy</h2><pre class="builder-code">plugin.json
plugin.php
resources/
  views/
    home.blade.php
    work-card.blade.php
public/
  plugin.css
database/
  migrations/        # optional
</pre><p class="muted">The ZIP root must contain <code>plugin.json</code> and <code>plugin.php</code>. New plugins install disabled and must be explicitly enabled by Super Admin.</p><h3>Core principle</h3><p>Use Scratchgard hooks/UI slots first. Do not patch core controllers or Blade files from a plugin. Plugin-owned data should live in plugin-owned tables.</p></div>
</div>

<div class="card"><div class="card-head"><div><h2>UI injection — WordPress-style extension points</h2><p class="muted">Render cards, buttons, notices, custom HTML or integration status inside existing Scratchgard screens. Visibility can be scoped by plugin permission, role and a context callback.</p></div></div>
<pre class="builder-code">$view = $p->permission('crm.view', 'View CRM integration');

$p->uiSlot(
    'work.show.after_vehicle',
    'crm-card',
    function (array $ctx, $user) {
        $work = $ctx['workOrder'];
        return '&lt;div class="card"&gt;CRM status for '.e($work->work_order_number).'&lt;/div&gt;';
    },
    permission: $view,
    roles: ['zonal_manager','super_admin'],
    when: fn(array $ctx) =&gt; ($ctx['workOrder']?-&gt;showroom?->vendor?->name ?? '') === 'Tata Motors',
    priority: 20
);</pre></div>

<div class="grid">
<div class="col-6 card"><h2>Tabs / sections</h2><pre class="builder-code">$p->uiTab(
  'work.show',
  'dealer-sync',
  'Dealer Sync',
  fn(array $ctx) =&gt; view('plugin-'.$p->slug().'::sync', $ctx)-&gt;render(),
  $view
);</pre></div>
<div class="col-6 card"><h2>Form fields</h2><pre class="builder-code">$p->formField('user.profile', 'certification_no', [
  'label' =&gt; 'PPF Certification Number',
  'type' =&gt; 'text',
  'required' =&gt; true,
  'help' =&gt; 'Stored/validated by the plugin through its hook or endpoint.'
], $view);</pre></div>
<div class="col-6 card"><h2>Table column</h2><pre class="builder-code">$p->tableColumn(
  'work_orders',
  'crm_status',
  'CRM',
  fn($work) =&gt; '&lt;span class="badge"&gt;Synced&lt;/span&gt;',
  $view
);</pre></div>
<div class="col-6 card"><h2>Row action</h2><pre class="builder-code">$p->tableAction(
  'work_orders',
  'sync',
  'Sync CRM',
  fn($work) =&gt; route('ext.my-plugin.sync', $work),
  $runPermission,
  method: 'GET'
);</pre></div>
</div>

<div class="card"><h2>Available UI slots</h2><p class="muted">These are stable named locations. Core updates can move markup internally without requiring the plugin to edit Scratchgard files.</p><div class="builder-grid">@foreach(($catalog['slots']??[]) as $slot=>$help)<div class="builder-item"><code>{{ $slot }}</code><p class="muted">{{ $help }}</p></div>@endforeach</div></div>
<div class="grid"><div class="col-4 card"><h2>Supported plugin tabs</h2>@foreach(($catalog['tabs']??[]) as $id=>$help)<span class="slot-chip">{{ $id }}</span><p class="muted">{{ $help }}</p>@endforeach</div><div class="col-4 card"><h2>Extensible forms</h2>@foreach(($catalog['forms']??[]) as $id=>$help)<span class="slot-chip">{{ $id }}</span><p class="muted">{{ $help }}</p>@endforeach</div><div class="col-4 card"><h2>Extensible tables</h2>@foreach(($catalog['tables']??[]) as $id=>$help)<span class="slot-chip">{{ $id }}</span><p class="muted">{{ $help }}</p>@endforeach</div></div>

<div class="card"><h2>Context visibility</h2><p class="muted">A plugin can show UI only for the right person and object. Core permission checks must still protect any route/action behind the UI.</p><pre class="builder-code">$p->uiSlot(
  'work.show.header.actions',
  'send-to-dealer',
  fn(array $ctx) =&gt; '&lt;a class="btn" href="..."&gt;Send to Dealer&lt;/a&gt;',
  permission: $sendPermission,
  roles: ['zonal_manager'],
  when: function (array $ctx, $user) {
      $work = $ctx['workOrder'];
      return $work-&gt;zone_id === $user-&gt;primary_zone_id
          &amp;&amp; $work-&gt;status === 'APPROVED';
  }
);</pre></div>

<div class="card"><h2>Feature permissions vs Plugin Manager</h2><p>Plugins may register their own capabilities such as <code>plugin.dealer-sync.sync.run</code>. Super Admin can grant those feature permissions to selected users/roles. <strong>That never grants access to Plugins administration.</strong> Installation, update, settings, migrations, activation, deactivation and deletion remain hard-restricted to actual <code>super_admin</code> accounts.</p></div>

<div class="card"><h2>Business hooks & integrations</h2><div class="builder-grid">
@foreach(['work.created','work.status.changed','work.approval.before','payment.eligible','payment.event','user.registered','user.identity.verified','kyc.reviewed','ticket.created','ticket.event','notification.recipients (filter)','notification.message (filter)','evidence.meta (filter)','evidence.stored','location.checkin','plugin.settings.updated'] as $hook)<div class="builder-item"><code>{{ $hook }}</code></div>@endforeach
</div><pre class="builder-code">$p->on('work.created', function ($work, $actor) {
    // queue dealer/CRM sync, create plugin-owned records, etc.
});

$p->filter('notification.recipients', function ($users, $eventType) {
    // safely add/remove recipients according to plugin policy
    return $users;
});</pre></div>

<div class="card"><h2>Plugin settings, routes, API, jobs and migrations</h2><pre class="builder-code">// plugin.json can declare generated Super Admin settings.
// Secret fields use "secret": true and are encrypted.

$p->webRoutes(function () use ($permission) {
    Route::get('/report', ...)-&gt;middleware('permission:'.$permission);
});

$p->apiRoutes(function () {
    Route::post('/sync', ...); // add the authentication/scope middleware your endpoint needs
});

$p->schedule(fn() =&gt; null, '*/15 * * * *', 'dealer-sync');

// Standard Laravel migrations may live under database/migrations/.
// Plugin-owned tables only; do not destructively alter Scratchgard core tables.</pre></div>

<div class="alert success"><strong>Recommended workflow:</strong> Generate starter ZIP → develop locally → review source → upload in Plugins → run migrations if required → enable → grant only its feature permissions to the required users → verify Health/Activity Log.</div>
@endsection
