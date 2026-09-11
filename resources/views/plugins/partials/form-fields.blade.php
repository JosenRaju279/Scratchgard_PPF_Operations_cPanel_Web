@php($pluginFields=app(\App\Plugins\PluginRuntime::class)->formFieldsFor($formName,$context??[],auth()->user()))
@foreach($pluginFields as $field)
@php
$d=$field['definition'];
$type=$d['type']??'text';$label=$d['label']??$field['key'];$required=!empty($d['required']);$placeholder=$d['placeholder']??'';$help=$d['help']??null;
$name='plugin_fields['.$field['plugin'].']['.$field['key'].']';
$value=$d['default']??'';
if(isset($d['value']) && is_callable($d['value'])){try{$value=$d['value']($context??[],auth()->user());}catch(\Throwable $e){$value='';}}
@endphp
<div class="{{ $d['class']??'col-6' }} field plugin-field" data-plugin="{{ $field['plugin'] }}" data-plugin-field="{{ $field['key'] }}">
<label>{{ $label }} @if($required)*@endif</label>
@if($type==='textarea')
<textarea name="{{ $name }}" placeholder="{{ $placeholder }}" @required($required)>{{ old('plugin_fields.'.$field['plugin'].'.'.$field['key'],$value) }}</textarea>
@elseif($type==='select')
<select name="{{ $name }}" @required($required)>@foreach(($d['options']??[]) as $k=>$v)<option value="{{ $k }}" @selected((string)old('plugin_fields.'.$field['plugin'].'.'.$field['key'],$value)===(string)$k)>{{ $v }}</option>@endforeach</select>
@elseif($type==='checkbox')
<label class="check"><input type="checkbox" name="{{ $name }}" value="1" @checked((bool)old('plugin_fields.'.$field['plugin'].'.'.$field['key'],$value))> {{ $d['checkbox_label']??$label }}</label>
@else
<input type="{{ in_array($type,['text','email','number','date','datetime-local','url','tel'],true)?$type:'text' }}" name="{{ $name }}" value="{{ old('plugin_fields.'.$field['plugin'].'.'.$field['key'],$value) }}" placeholder="{{ $placeholder }}" @required($required)>
@endif
@if($help)<small class="muted">{{ $help }}</small>@endif
</div>
@endforeach
