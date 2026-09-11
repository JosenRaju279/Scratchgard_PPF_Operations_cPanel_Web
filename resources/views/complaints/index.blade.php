@extends('layouts.app')
@section('title','Complaints & Rework')
@section('content')
<div class="page-head"><div><p class="eyebrow">Quality & tickets</p><h1>Complaints & rework</h1><p class="muted">Every complaint stays linked to the original Work Order, VIN, evidence, ticket, rework and payment history.</p></div></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Work / VIN</th><th>Ticket</th><th>Severity</th><th>Description</th><th>Reworks</th><th>Status</th><th>Created</th></tr></thead><tbody>
@forelse($complaints as $c)<tr>
<td><a href="{{ route('work-orders.show',$c->work_order_id) }}"><strong>{{ $c->workOrder?->work_order_number }}</strong></a><br><small class="muted">{{ $c->workOrder?->vehicle?->vin }}</small></td>
<td>@if($c->ticket)<a href="{{ route('tickets.show',$c->ticket) }}">{{ $c->ticket->ticket_number }}</a>@else—@endif</td>
<td><span class="badge">{{ strtoupper($c->severity) }}</span></td><td>{{ $c->description }}</td>
<td>{{ $c->reworks->count() }}@foreach($c->reworks as $r)<br><small class="muted">{{ $r->reference }} · {{ $r->scope_classification }}</small>@endforeach</td>
<td><span class="badge">{{ strtoupper($c->status) }}</span></td><td>{{ $c->created_at?->format('d M Y H:i') }}</td></tr>
@empty<tr><td colspan="7" class="muted">No complaints yet.</td></tr>@endforelse
</tbody></table></div>{{ $complaints->links() }}</div>
@endsection
