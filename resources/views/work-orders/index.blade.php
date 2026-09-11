@extends('layouts.app')
@section('title','Work Orders')
@section('content')
@php
$pr=app(\App\Plugins\PluginRuntime::class);
$pluginColumns=$pr->tableColumnsFor('work_orders',['screen'=>'index'],auth()->user());
$hasPluginActions=$workOrders->getCollection()->contains(fn($w)=>count($pr->tableActionsFor('work_orders',$w,['screen'=>'index'],auth()->user()))>0);
@endphp
<div class="page-head"><div><p class="eyebrow">Operations</p><h1>Work orders</h1><p class="muted">Search by work order, VIN, registration, vehicle or showroom.</p></div>@if(auth()->user()->hasPermission('work.create'))<a class="btn" href="{{ route('work-orders.create') }}">+ New work</a>@endif</div>
<div class="plugin-slot">{!! $pr->renderSlot('work.index.before_filters',['workOrders'=>$workOrders],auth()->user()) !!}</div>
<form class="card searchbar" method="get"><input name="q" value="{{ request('q') }}" placeholder="Search work order, VIN, registration, vehicle or showroom…"><button class="btn secondary">Search</button>@if(request('q'))<a class="btn ghost" href="{{ route('work-orders.index') }}">Clear</a>@endif</form>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Work</th><th>VIN / Vehicle</th><th>Showroom</th><th>Zone</th><th>Status</th><th>Applicator</th>@foreach($pluginColumns as $c)<th>{{ $c['label'] }}</th>@endforeach @if($hasPluginActions)<th>Extensions</th>@endif</tr></thead><tbody>
@forelse($workOrders as $w)<tr><td><a href="{{ route('work-orders.show',$w) }}"><strong>{{ $w->work_order_number }}</strong></a></td><td><span class="vin-mini">{{ $w->vehicle?->vin }}</span><br>{{ $w->vehicle?->make }} {{ $w->vehicle?->model }}</td><td>{{ $w->showroom?->name }}</td><td>{{ $w->zone?->code }}</td><td><span class="badge">{{ $w->status }}</span></td><td>{{ $w->applicator?->name ?? 'Unassigned' }}</td>@foreach($pluginColumns as $c)<td>{!! $pr->renderTableCell($c,$w,['screen'=>'index'],auth()->user()) !!}</td>@endforeach @if($hasPluginActions)<td>@include('plugins.partials.table-actions',['actions'=>$pr->tableActionsFor('work_orders',$w,['screen'=>'index'],auth()->user())])</td>@endif</tr>
@empty<tr><td colspan="{{ 6+count($pluginColumns)+($hasPluginActions?1:0) }}" class="muted">No matching work.</td></tr>@endforelse
</tbody></table></div>{{ $workOrders->links() }}</div>
<div class="plugin-slot">{!! $pr->renderSlot('work.index.after_table',['workOrders'=>$workOrders],auth()->user()) !!}</div>
@endsection
