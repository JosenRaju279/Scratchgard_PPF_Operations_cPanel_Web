<div class="plugin-action-list">
@foreach($actions as $a)
@php($method=strtoupper($a['method']??'GET'))
@if($method==='GET')
<a class="btn secondary small" href="{{ $a['url'] }}">{{ $a['label'] }}</a>
@else
<form method="post" action="{{ $a['url'] }}" style="display:inline">@csrf @if(!in_array($method,['POST'],true))@method($method)@endif<button class="btn secondary small" type="submit">{{ $a['label'] }}</button></form>
@endif
@endforeach
</div>
