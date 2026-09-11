@extends('layouts.app')
@section('title',($user->role?->slug==='super_admin'?'Super Admin ':'').'Dashboard')
@section('content')
@php
$isSuper=$user->role?->slug==='super_admin';
$roleLabel=$user->role?->name ?? 'User';
$today=now();
$donutParts=[];$cursor=0;
foreach($statusSplit as $s){$next=$cursor+$s['percent'];if($s['percent']>0)$donutParts[]=$s['color'].' '.$cursor.'% '.$next.'%';$cursor=$next;}
$donutStyle=count($donutParts)?'conic-gradient('.implode(',',$donutParts).')':'#edf2f7';
$statusClass=fn($status)=>match(true){in_array($status,['APPROVED','PAID','CLOSED','PAYMENT_ELIGIBLE'],true)=>'green',in_array($status,['WORK_IN_PROGRESS','AT_LOCATION','BEFORE_EVIDENCE_COMPLETE'],true)=>'blue',in_array($status,['WORK_SUBMITTED','AWAITING_EXTERNAL_REVIEW'],true)=>'purple',in_array($status,['CORRECTION_REQUIRED','REWORK_REQUIRED','QUALITY_HOLD','REJECTED'],true)=>'red',default=>'amber'};
@endphp
<div class="erp-dashboard-head">
    <div>
        <h1>{{ $isSuper?'Super Admin Dashboard':'Operations Dashboard' }}</h1>
        <h2>{{ $brandName ?? 'Scratchgard' }} PPF Operations ERP</h2>
        <p>Monitor operations, quality, assignments and delivery from one role-scoped workspace.</p>
    </div>
    <div class="erp-date-chip"><x-icon name="calendar" /><span><small>Today</small><strong>{{ $today->format('M d, Y') }}</strong></span></div>
</div>

<div class="plugin-slot">{!! app(\App\Plugins\PluginRuntime::class)->renderSlot('dashboard.before_metrics',['user'=>$user],$user) !!}</div>

<section class="erp-kpi-grid">
    <article class="erp-kpi-card"><span class="kpi-icon blue"><x-icon name="work" /></span><div><small>Total Work Orders</small><strong>{{ number_format($stats['total']) }}</strong><em>All visible work</em></div></article>
    <article class="erp-kpi-card"><span class="kpi-icon amber"><x-icon name="clock" /></span><div><small>Pending Approvals</small><strong>{{ number_format($stats['pending_review']) }}</strong><em>Awaiting decision</em></div></article>
    <article class="erp-kpi-card"><span class="kpi-icon blue"><x-icon name="users" /></span><div><small>Active Applicators</small><strong>{{ number_format($stats['active_applicators']) }}</strong><em>Ready / operational</em></div></article>
    <article class="erp-kpi-card"><span class="kpi-icon blue"><x-icon name="zones" /></span><div><small>Active Zones</small><strong>{{ number_format($stats['active_zones']) }}</strong><em>Mapped coverage</em></div></article>
    <article class="erp-kpi-card"><span class="kpi-icon red"><x-icon name="tickets" /></span><div><small>Open Tickets</small><strong>{{ number_format($stats['open_tickets']) }}</strong><em>Needs attention</em></div></article>
    <article class="erp-kpi-card"><span class="kpi-icon purple"><x-icon name="refresh" /></span><div><small>Reworks</small><strong>{{ number_format($stats['rework']) }}</strong><em>Correction / quality hold</em></div></article>
    <article class="erp-kpi-card"><span class="kpi-icon green"><x-icon name="verifier" /></span><div><small>Active Showrooms</small><strong>{{ number_format($stats['active_showrooms']) }}</strong><em>Vendor locations</em></div></article>
    <article class="erp-kpi-card"><span class="kpi-icon blue"><x-icon name="payments" /></span><div><small>Payment Status</small><strong>{{ $stats['payment_percent'] }}%</strong><em>Recorded as paid</em></div></article>
</section>

<div class="plugin-slot">{!! app(\App\Plugins\PluginRuntime::class)->renderSlot('dashboard.after_metrics',['user'=>$user,'stats'=>$stats],$user) !!}</div>

<section class="erp-dashboard-grid top-grid">
    <article class="erp-panel activity-panel">
        <div class="erp-panel-head"><div><x-icon name="chart" /><strong>Weekly Work Activity</strong></div><div class="chart-legend"><span class="dot blue"></span>New <span class="dot green"></span>Approved <span class="dot red"></span>Rework</div></div>
        <div class="weekly-chart" aria-label="Weekly work activity chart">
            <div class="chart-y"><span>{{ $weeklyMax }}</span><span>{{ (int)ceil($weeklyMax*.75) }}</span><span>{{ (int)ceil($weeklyMax*.5) }}</span><span>{{ (int)ceil($weeklyMax*.25) }}</span><span>0</span></div>
            <div class="chart-bars">
                @foreach($weekly as $day)
                    @php $sum=max(1,$day['new']+$day['approved']+$day['rework']);$height=max(5,(($day['new']+$day['approved']+$day['rework'])/$weeklyMax)*100); @endphp
                    <div class="bar-day"><div class="bar-stack" style="height:{{ $height }}%">
                        @if($day['rework'])<i class="bar rework" style="height:{{ ($day['rework']/$sum)*100 }}%" title="{{ $day['rework'] }} rework"></i>@endif
                        @if($day['approved'])<i class="bar approved" style="height:{{ ($day['approved']/$sum)*100 }}%" title="{{ $day['approved'] }} approved"></i>@endif
                        @if($day['new'])<i class="bar new" style="height:{{ ($day['new']/$sum)*100 }}%" title="{{ $day['new'] }} new"></i>@endif
                    </div><span>{{ $day['label'] }}</span></div>
                @endforeach
            </div>
        </div>
    </article>

    <article class="erp-panel status-panel">
        <div class="erp-panel-head"><div><x-icon name="chart" /><strong>Work Status Split</strong></div></div>
        <div class="status-split-wrap">
            <div class="status-donut" style="--donut:{{ $donutStyle }}"><div><strong>{{ number_format($stats['total']) }}</strong><small>Work Orders</small></div></div>
            <div class="status-legend">
                @foreach($statusSplit as $s)<div><span class="legend-color" style="background:{{ $s['color'] }}"></span><span>{{ $s['label'] }}</span><strong>{{ $s['percent'] }}% <small>({{ $s['count'] }})</small></strong></div>@endforeach
            </div>
        </div>
    </article>

    <article class="erp-panel actions-panel">
        <div class="erp-panel-head"><div><x-icon name="check" /><strong>Pending Actions</strong></div></div>
        <div class="pending-list">
            @forelse($pendingActions as $action)
                <div class="pending-row"><span class="pending-icon"><x-icon :name="$action['icon']" /></span><strong>{{ $action['label'] }}</strong><b>{{ $action['count'] }}</b><a href="{{ $action['url'] }}">{{ $action['action'] }}</a></div>
            @empty<div class="empty-state">No pending actions.</div>@endforelse
        </div>
    </article>
</section>

<section class="erp-dashboard-grid middle-grid {{ $zonePerformance->count()?'':'single' }}">
    @if($zonePerformance->count())
    <article class="erp-panel zone-panel">
        <div class="erp-panel-head"><div><x-icon name="zones" /><strong>Zone Performance</strong></div>@if($user->hasPermission('masters.manage'))<a href="{{ route('masters.index') }}">View all <x-icon name="arrow" size="15" /></a>@endif</div>
        <div class="table-wrap clean"><table class="erp-table"><thead><tr><th>Zone</th><th>Manager</th><th>Open Jobs</th><th>Approvals</th><th>Reworks</th><th>Workload</th></tr></thead><tbody>
            @forelse($zonePerformance as $z)<tr><td><strong>{{ $z['name'] }}</strong><small>{{ $z['code'] }}</small></td><td>{{ $z['manager'] }}</td><td>{{ $z['open'] }}</td><td>{{ $z['approved'] }}</td><td class="red-text">{{ $z['rework'] }}</td><td><div class="load-cell"><span><i style="width:{{ $z['load'] }}%"></i></span><b>{{ $z['load'] }}%</b></div></td></tr>@empty<tr><td colspan="6" class="muted">No zone activity yet.</td></tr>@endforelse
        </tbody></table></div>
    </article>
    @endif

    <article class="erp-panel recent-panel">
        <div class="erp-panel-head"><div><x-icon name="work" /><strong>Recent Work Orders</strong></div><a href="{{ route('work-orders.index') }}">View all <x-icon name="arrow" size="15" /></a></div>
        <div class="table-wrap clean"><table class="erp-table"><thead><tr><th>WO ID</th><th>VIN / Vehicle</th><th>Showroom</th><th>Applicator</th><th>Status</th><th>Payment</th><th>Tracker</th></tr></thead><tbody>
            @forelse($recent as $w)<tr>
                <td><a href="{{ route('work-orders.show',$w) }}"><strong>{{ $w->work_order_number }}</strong></a></td>
                <td><span class="vin-mini">{{ $w->vehicle?->vin ?: '—' }}</span><small>{{ trim(($w->vehicle?->make??'').' '.($w->vehicle?->model??'')) }}</small></td>
                <td>{{ $w->showroom?->name ?: '—' }}</td><td>{{ $w->applicator?->name ?? 'Unassigned' }}</td>
                <td><span class="status-pill {{ $statusClass($w->status) }}">{{ ucwords(strtolower(str_replace('_',' ',$w->status))) }}</span></td>
                <td><span class="status-pill {{ $w->payment?->status==='paid'?'green':'amber' }}">{{ $w->payment?->status ? ucfirst($w->payment->status) : 'Pending' }}</span></td>
                <td>@if($w->tracking_enabled && $w->tracking_token)<a class="table-link" href="{{ route('tracking.show',$w->tracking_token) }}" target="_blank">View ↗</a>@else<span class="muted">Off</span>@endif</td>
            </tr>@empty<tr><td colspan="7" class="muted">No work orders yet.</td></tr>@endforelse
        </tbody></table></div>
    </article>
</section>

@if($applicatorWorkloads->count())
<section class="erp-panel workload-panel"><div class="erp-panel-head"><div><x-icon name="applicator" /><strong>Applicator Workload</strong></div><small>Open work before new assignment</small></div><div class="erp-workload-grid">
@foreach($applicatorWorkloads as $a)<article><div><strong>{{ $a->name }}</strong><small>PIN {{ $a->pincode ?: '—' }}</small></div><b>{{ $a->open_count }}</b><div class="mini-workload"><span>Active {{ $a->active_count }}</span><span>Review {{ $a->review_count }}</span><span>Rework {{ $a->rework_count }}</span></div></article>@endforeach
</div></section>
@endif

@if(!empty($pluginWidgets))<section class="erp-dashboard-grid extension-grid">@foreach($pluginWidgets as $widget)<article class="erp-panel"><div class="erp-panel-head"><div><x-icon name="plugins" /><strong>{{ $widget['title'] }}</strong></div><small>{{ $widget['plugin'] }}</small></div>{!! $widget['html'] !!}</article>@endforeach</section>@endif

<section class="erp-dashboard-grid health-grid">
    @if($isSuper)
    <article class="erp-panel health-panel"><div class="erp-panel-head"><div><x-icon name="plugins" /><strong>Plugin Manager</strong></div><a href="{{ route('plugins.index') }}">Manage Plugins <x-icon name="arrow" size="15" /></a></div><div class="health-cards"><div><span class="kpi-icon green"><x-icon name="plugins" /></span><strong>{{ $pluginSummary['enabled'] }}</strong><small>Enabled Plugins<br>out of {{ $pluginSummary['total'] }} installed</small></div><div><span class="kpi-icon red"><x-icon name="alert" /></span><strong>{{ $pluginSummary['failed'] }}</strong><small>Failed Health<br>checks / runtime</small></div><a class="builder-button" href="{{ route('plugins.builder') }}">&lt;/&gt; Plugin Builder Helper<small>Create and extend Scratchgard</small></a></div></article>
    @endif
    <article class="erp-panel health-panel"><div class="erp-panel-head"><div><x-icon name="mail" /><strong>Notifications & Email Delivery</strong></div>@if($user->hasPermission('settings.manage'))<a href="{{ route('settings.index') }}">Settings <x-icon name="arrow" size="15" /></a>@endif</div><div class="health-cards mail-health"><div><span class="kpi-icon blue"><x-icon name="mail" /></span><strong>{{ number_format($mailSummary['sent']) }}</strong><small>Emails Sent<br>last 7 days</small></div><div><span class="rate-ring">{{ $mailSummary['rate'] }}%</span><small>Delivery Rate</small></div><div><span class="kpi-icon red"><x-icon name="alert" /></span><strong>{{ $mailSummary['failed'] }}</strong><small>Failed Deliveries</small></div></div></article>
    <article class="erp-panel health-panel"><div class="erp-panel-head"><div><x-icon name="api" /><strong>Public Tracker / API Health</strong></div></div><div class="health-cards api-health"><div><span class="kpi-icon blue"><x-icon name="globe" /></span><strong class="health-ok"><i></i> Online</strong><small>{{ number_format($systemSummary['tracking_enabled']) }} tracking links enabled</small></div>@if($systemSummary['api_active']!==null)<div><span class="kpi-icon green"><x-icon name="heart" /></span><strong class="health-ok"><i></i> Healthy</strong><small>{{ $systemSummary['api_active'] }} active API clients</small></div>@endif</div></article>
</section>

<div class="plugin-slot">{!! app(\App\Plugins\PluginRuntime::class)->renderSlot('dashboard.after_recent_work',['user'=>$user,'recent'=>$recent],$user) !!}</div>
@endsection
