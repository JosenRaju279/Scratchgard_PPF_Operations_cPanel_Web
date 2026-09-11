@extends('layouts.app')
@section('title','Evidence')
@section('content')
<div class="page-head"><div><p class="eyebrow">Quality evidence</p><h1>Evidence library</h1><p class="muted">Role-scoped before, after, during and rework evidence linked to work orders.</p></div></div>
<form class="card searchbar" method="get"><input name="q" value="{{ request('q') }}" placeholder="Search work order or VIN…"><select name="stage"><option value="">All stages</option>@foreach(['BEFORE','DURING','AFTER','REWORK_BEFORE','REWORK_AFTER'] as $s)<option value="{{ $s }}" @selected(request('stage')===$s)>{{ str_replace('_',' ',$s) }}</option>@endforeach</select><button class="btn secondary">Filter</button>@if(request('q')||request('stage'))<a class="btn ghost" href="{{ route('evidence.index') }}">Clear</a>@endif</form>
<div class="evidence-library-grid">
@forelse($items as $e)
<article class="evidence-library-card"><a class="evidence-thumb" href="{{ route('evidence.view',$e) }}" target="_blank"><span><x-icon name="evidence" size="28" /></span><small>Open evidence</small></a><div class="evidence-meta"><strong>{{ $e->workOrder?->work_order_number }}</strong><span>{{ str_replace('_',' ',$e->stage) }} · {{ $e->vehicle_area ?: $e->slot_code }}</span><small>{{ $e->workOrder?->vehicle?->vin }} · {{ $e->user?->name }}</small></div></article>
@empty<div class="card">No evidence found.</div>@endforelse
</div>
{{ $items->links() }}
@endsection
