@extends('layouts.app')
@section('title','Create Work Order')
@section('content')
<h1>Create Work Order</h1>
<form method="post" action="{{ route('work-orders.store') }}">@csrf
<div class="card"><h2>Vehicle</h2><div class="grid">
<div class="col-6 field"><label>VIN / Chassis Number *</label><input name="vin" value="{{ old('vin') }}" required></div><div class="col-6 field"><label>Registration Number</label><input name="registration_number" value="{{ old('registration_number') }}"></div>
<div class="col-4 field"><label>Make *</label><input name="make" value="{{ old('make') }}" required></div><div class="col-4 field"><label>Model *</label><input name="model" value="{{ old('model') }}" required></div><div class="col-4 field"><label>Variant</label><input name="variant"></div><div class="col-4 field"><label>Color</label><input name="color"></div>
</div></div>
<div class="card"><h2>Job</h2><div class="grid">
<div class="col-6 field"><label>Showroom *</label><select name="showroom_id" required><option value="">Select</option>@foreach($showrooms as $s)<option value="{{ $s->id }}">{{ $s->vendor?->name }} — {{ $s->name }} ({{ $s->pincode }})</option>@endforeach</select></div>
<div class="col-6 field"><label>Zone *</label><select name="zone_id" required><option value="">Select</option>@foreach($zones as $z)<option value="{{ $z->id }}">{{ $z->code }} — {{ $z->name }}</option>@endforeach</select></div>
<div class="col-4 field"><label>Package code *</label><input name="package_code" placeholder="FULL_PPF" required></div><div class="col-4 field"><label>Job type</label><input name="job_type" placeholder="Installation"></div><div class="col-4 field"><label>Scheduled at</label><input type="datetime-local" name="scheduled_at"></div>
<div class="col-6 field"><label>External reference</label><input name="external_reference"></div><div class="col-6 field"><label>Fixed applicator payment</label><input type="number" step="0.01" name="pricing_amount"></div>
<div class="col-12 field"><label>Notes</label><textarea name="notes"></textarea></div>
@include('plugins.partials.form-fields',['formName'=>'work.create','context'=>['zones'=>$zones,'showrooms'=>$showrooms]])
</div></div>
<button class="btn good">Create Work Order</button>
</form>
@endsection
