@extends('layouts.app')
@section('title','Payments')
@section('content')
@php
$pr=app(\App\Plugins\PluginRuntime::class);$paymentCols=$pr->tableColumnsFor('payments',['screen'=>'index'],auth()->user());$hasPaymentActions=$payments->getCollection()->contains(fn($p)=>count($pr->tableActionsFor('payments',$p,['screen'=>'index'],auth()->user()))>0);
@endphp
<div class="page-head"><div><p class="eyebrow">Finance ledger</p><h1>Payments</h1><p class="muted">Approval creates one payment-eligible record. Later complaints/rework do not rewrite a paid payment. Status correction is privileged and audited.</p></div><div><a class="btn secondary" href="{{ route('payments.export') }}">Export CSV for finance/Tally mapping</a></div></div>
<div class="plugin-slot">{!! $pr->renderSlot('payment.index.before_table',['payments'=>$payments],auth()->user()) !!}</div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Work</th><th>VIN</th><th>Eligible</th><th>Status</th><th>Paid</th>@foreach($paymentCols as $c)<th>{{ $c['label'] }}</th>@endforeach<th>Finance action</th>@if($hasPaymentActions)<th>Extensions</th>@endif</tr></thead><tbody>
@forelse($payments as $p)<tr>
<td><a href="{{ route('work-orders.show',$p->work_order_id) }}"><strong>{{ $p->workOrder?->work_order_number }}</strong></a>@if($p->previous_status)<br><small class="muted">Previous: {{ $p->previous_status }}</small>@endif</td>
<td>{{ $p->workOrder?->vehicle?->vin ?: '—' }}</td>
<td>{{ $p->currency }} {{ number_format((float)$p->eligible_amount,2) }}</td>
<td><span class="badge">{{ $p->status==='paid' && $p->mode==='status_based_approval' ? 'DONE' : strtoupper($p->status) }}</span>@if($p->held_at)<br><small class="muted">Held {{ $p->held_at->format('d M Y H:i') }}</small>@endif @if($p->reverted_at)<br><small class="muted">Corrected {{ $p->reverted_at->format('d M Y H:i') }}</small>@endif</td>
<td>@if($p->status==='paid')<strong>{{ $p->currency }} {{ number_format((float)$p->paid_amount,2) }}</strong><br><span class="muted">{{ optional($p->paid_at)->format('d M Y H:i') }} · {{ $p->mode }} {{ $p->reference }}</span>@else—@endif</td>
@foreach($paymentCols as $c)<td>{!! $pr->renderTableCell($c,$p,['screen'=>'index'],auth()->user()) !!}</td>@endforeach
<td>
@if(auth()->user()->hasPermission('payment.manage') && !in_array($p->status,['paid','held'],true))
<form method="post" action="{{ route('payments.paid',$p) }}" class="compact-form">@csrf
<input type="number" step="0.01" name="paid_amount" value="{{ $p->eligible_amount }}" required><input type="datetime-local" name="paid_at" required><input name="mode" placeholder="UPI / Bank" required><input name="reference" placeholder="Reference"><button class="btn good small">Mark paid</button>
</form>
@endif
@if(auth()->user()->role?->slug==='super_admin' || auth()->user()->hasPermission('payment.revert'))
<details class="inline-details"><summary>Privileged status correction</summary><form method="post" action="{{ route('payments.revert',$p) }}" class="compact-form">@csrf
<select name="target_status" required><option value="eligible">Eligible</option><option value="held">Held</option><option value="reversed">Reversed</option></select>
<select name="reason_id" required><option value="">Reason</option>@foreach($revertReasons as $r)<option value="{{ $r->id }}">{{ $r->label }}</option>@endforeach</select>
<input name="notes" placeholder="Mandatory audit note" required><button class="btn danger small">Apply correction</button></form></details>
@endif
</td>@if($hasPaymentActions)<td>@include('plugins.partials.table-actions',['actions'=>$pr->tableActionsFor('payments',$p,['screen'=>'index'],auth()->user())])</td>@endif</tr>@empty<tr><td colspan="{{ 6+count($paymentCols)+($hasPaymentActions?1:0) }}" class="muted">No payment records yet.</td></tr>@endforelse
</tbody></table></div>{{ $payments->links() }}</div>
<div class="plugin-slot">{!! $pr->renderSlot('payment.index.after_table',['payments'=>$payments],auth()->user()) !!}</div>
@endsection
