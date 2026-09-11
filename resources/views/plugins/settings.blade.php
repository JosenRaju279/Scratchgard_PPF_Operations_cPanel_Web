@extends('layouts.app')
@section('title',$plugin->name.' Settings')
@section('content')
<div class="page-head"><div><p class="eyebrow">Super Admin · Plugin Settings</p><h1>{{ $plugin->name }}</h1><p class="muted">Settings marked secret are encrypted before being stored in the database.</p></div><a class="btn secondary" href="{{ route('plugins.show',$plugin) }}">← Plugin</a></div>
<form class="card" method="post" action="{{ route('plugins.settings.save',$plugin) }}">@csrf
@forelse($plugin->settings_schema??[] as $field)@php($key=$field['key']??'') @php($type=$field['type']??'text') @php($value=$values[$key]??($field['default']??null))
<div class="field"><label>{{ $field['label']??$key }} @if(!empty($field['required']))<span>*</span>@endif</label>
@if($type==='boolean')<label><input type="checkbox" name="settings[{{ $key }}]" value="1" @checked((bool)$value)> Enabled</label>
@elseif($type==='select')<select name="settings[{{ $key }}]">@foreach($field['options']??[] as $optValue=>$optLabel)<option value="{{ is_int($optValue)?$optLabel:$optValue }}" @selected((string)$value===(string)(is_int($optValue)?$optLabel:$optValue))>{{ $optLabel }}</option>@endforeach</select>
@elseif($type==='textarea')<textarea name="settings[{{ $key }}]" rows="4">{{ $value }}</textarea>
@elseif($type==='number')<input type="number" step="any" name="settings[{{ $key }}]" value="{{ $value }}">
@elseif(!empty($field['secret']))<input type="password" name="settings[{{ $key }}]" value="" placeholder="Leave blank to keep existing secret"><small class="muted">Stored encrypted. Existing secret is never displayed.</small>
@else<input type="text" name="settings[{{ $key }}]" value="{{ is_scalar($value)?$value:json_encode($value) }}">@endif
@if(!empty($field['help']))<small class="muted">{{ $field['help'] }}</small>@endif</div>
@empty<p>This plugin has no configurable settings.</p>@endforelse
@if(count($plugin->settings_schema??[]))<button class="btn">Save plugin settings</button>@endif
</form>
@endsection
