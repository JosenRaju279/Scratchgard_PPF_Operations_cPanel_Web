@extends('layouts.app')
@section('title','Plugins')
@section('content')
<div class="page-head"><div><p class="eyebrow">Super Admin · Extension Framework</p><h1>Scratchgard plugins</h1><p class="muted">Install trusted extensions that can integrate with Scratchgard hooks, APIs, workflows, dashboards, UI slots, forms, tables, schedules and external systems. Plugin management is hard-restricted to Super Admin.</p></div><div class="page-actions"><a class="btn secondary" href="{{ route('plugins.builder') }}">Plugin Builder Helper</a></div></div>
<div class="grid">
<div class="col-4 card"><h2>Install / update plugin</h2><p class="muted">Upload a trusted ZIP containing <code>plugin.json</code> and <code>plugin.php</code>. New plugins install disabled. Uploading the same slug performs an update with compatibility checks.</p><form method="post" enctype="multipart/form-data" action="{{ route('plugins.install') }}">@csrf<div class="field"><label>Plugin ZIP</label><input type="file" name="plugin" accept=".zip" required></div><button class="btn">Install / update disabled</button></form><hr><small class="muted">Plugins execute trusted server-side PHP. Review source before enabling. Do not install untrusted third-party ZIPs.</small></div>
<div class="col-8 card"><div class="card-head"><div><h2>Installed plugins</h2><p class="muted">Compatibility, dependencies and runtime faults are visible before activation.</p></div></div><div class="table-wrap"><table><thead><tr><th>Plugin</th><th>Version</th><th>Status</th><th>Health</th><th></th></tr></thead><tbody>
@forelse($plugins as $p)@php($h=$health[$p->id]??[])
<tr><td><strong>{{ $p->name }}</strong><br><span class="muted">{{ $p->slug }}@if($p->author) · {{ $p->author }}@endif</span></td><td>{{ $p->version ?: '—' }}</td><td><span class="badge">{{ $p->enabled?'enabled':'disabled' }}</span></td><td>@if(($h['files']??false)&&($h['compatible']??false)&&($h['dependencies']??false)&&empty($p->last_error))<span class="badge">healthy</span>@else<span class="badge">attention</span>@endif</td><td><a class="btn secondary small" href="{{ route('plugins.show',$p) }}">Manage</a></td></tr>
@empty<tr><td colspan="5">No plugins installed.</td></tr>@endforelse
</tbody></table></div></div></div>
@endsection
