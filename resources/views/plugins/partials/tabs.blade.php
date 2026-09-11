@php($pluginTabs=app(\App\Plugins\PluginRuntime::class)->tabsFor($screen,$context??[],auth()->user()))
@if(count($pluginTabs))
<div class="plugin-tabs" data-plugin-tabs>
<div class="plugin-tab-nav">@foreach($pluginTabs as $i=>$tab)<button type="button" class="plugin-tab-btn {{ $i===0?'active':'' }}" data-target="plugin-tab-{{ md5($screen.$tab['plugin'].$tab['id']) }}">{{ $tab['label'] }}</button>@endforeach</div>
@foreach($pluginTabs as $i=>$tab)<div id="plugin-tab-{{ md5($screen.$tab['plugin'].$tab['id']) }}" class="plugin-tab-panel {{ $i===0?'active':'' }}">{!! $tab['html'] !!}</div>@endforeach
</div>
@push('scripts')<script>document.querySelectorAll('[data-plugin-tabs]').forEach(root=>{root.querySelectorAll('.plugin-tab-btn').forEach(btn=>btn.addEventListener('click',()=>{root.querySelectorAll('.plugin-tab-btn,.plugin-tab-panel').forEach(x=>x.classList.remove('active'));btn.classList.add('active');root.querySelector('#'+btn.dataset.target)?.classList.add('active')}))});</script>@endpush
@endif
