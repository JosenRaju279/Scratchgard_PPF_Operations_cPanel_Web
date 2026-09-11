@extends('layouts.app')
@section('title','KYC Review')
@section('content')
<h1>KYC Review</h1>
@foreach($records as $k)
<div class="card"><div class="grid"><div class="col-6"><h2>{{ $k->user?->name }}</h2><p>{{ $k->user?->email }} · {{ $k->user?->mobile }}</p><p><strong>PIN:</strong> {{ $k->user?->pincode }} · {{ $k->user?->district }} · {{ $k->user?->state }}</p><p><strong>Document:</strong> {{ $k->document_type }} — {{ $k->document_number }}</p></div><div class="col-6"><span class="badge">{{ $k->status }}</span><p class="muted">Submitted {{ $k->created_at?->format('d M Y H:i') }}</p></div></div>
<form method="post" action="{{ route('kyc.review',$k) }}">@csrf<div class="grid"><div class="col-4 field"><label>Decision</label><select name="decision"><option value="approved">Approve</option><option value="resubmission_required">Request resubmission</option><option value="rejected">Reject</option></select></div><div class="col-4 field"><label>Reason</label><select name="reason_id"><option value="">Optional</option>@foreach($reasons as $r)<option value="{{ $r->id }}">{{ $r->label }}</option>@endforeach</select></div><div class="col-4 field"><label>Notes</label><input name="notes"></div></div><button class="btn good">Record KYC decision</button></form></div>
@endforeach
{{ $records->links() }}
@endsection
