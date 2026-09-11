<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="{{ $brandTheme['theme'] ?? '#101c2c' }}">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="stylesheet" href="/assets/app.css">
@if(!empty($brandFaviconUrl))<link rel="icon" href="{{ $brandFaviconUrl }}">@endif
<style>:root{--brand-bg:{{ $brandTheme['theme'] ?? '#101c2c' }};--accent:{{ $brandTheme['accent'] ?? '#1677ff' }};--brand-text:{{ $brandTheme['text'] ?? '#edf7ff' }};--app-font:{{ $brandTheme['font'] ?? 'Inter,ui-sans-serif,system-ui,sans-serif' }};--base-size:{{ $brandTheme['base'] ?? 16 }}px;--heading-scale:{{ $brandTheme['heading'] ?? 1.22 }};}</style>
{!! app(\App\Plugins\PluginRuntime::class)->renderSlot('layout.head',['route'=>request()->route()?->getName()],auth()->user()) !!}
<title>@yield('title',$brandName ?? 'Scratchgard')</title>
</head>
<body class="{{ auth()->check() ? 'erp-auth' : 'erp-guest' }}">
@auth
@php
$user=auth()->user();
$isSuper=$user->role?->slug==='super_admin';
$nav=[
 ['label'=>'Dashboard','icon'=>'dashboard','url'=>route('dashboard'),'active'=>request()->routeIs('dashboard'),'show'=>true],
 ['label'=>'Work Orders','icon'=>'work','url'=>route('work-orders.index'),'active'=>request()->routeIs('work-orders.*'),'show'=>true],
 ['label'=>'Zones','icon'=>'zones','url'=>route('masters.index'),'active'=>request()->routeIs('masters.*'),'show'=>$user->hasPermission('masters.manage')],
 ['label'=>'Users','icon'=>'users','url'=>route('users.index'),'active'=>request()->routeIs('users.*')&&!request()->filled('role'),'show'=>$user->hasPermission('users.manage')],
 ['label'=>'Applicators','icon'=>'applicator','url'=>route('users.index',['role'=>'applicator']),'active'=>request()->routeIs('users.index')&&request('role')==='applicator','show'=>$user->hasPermission('users.manage')],
 ['label'=>'Showrooms','icon'=>'showroom','url'=>route('masters.index').'#showrooms','active'=>false,'show'=>$user->hasPermission('masters.manage')],
 ['label'=>'Verifiers','icon'=>'verifier','url'=>route('users.index',['role'=>'external_verifier']),'active'=>request()->routeIs('users.index')&&request('role')==='external_verifier','show'=>$user->hasPermission('users.manage')],
 ['label'=>'KYC','icon'=>'kyc','url'=>route('kyc.index'),'active'=>request()->routeIs('kyc.*'),'show'=>$user->hasPermission('kyc.review')],
 ['label'=>'Evidence','icon'=>'evidence','url'=>route('evidence.index'),'active'=>request()->routeIs('evidence.*'),'show'=>$isSuper||$user->hasPermission('work.override')||in_array($user->role?->slug,['zonal_manager','applicator'],true)],
 ['label'=>'Tickets','icon'=>'tickets','url'=>route('tickets.index'),'active'=>request()->routeIs('tickets.*'),'show'=>$user->hasPermission('tickets.view')],
 ['label'=>'Payments','icon'=>'payments','url'=>route('payments.index'),'active'=>request()->routeIs('payments.*'),'show'=>$user->hasPermission('payment.view')],
 ['label'=>'Plugins','icon'=>'plugins','url'=>route('plugins.index'),'active'=>request()->routeIs('plugins.*'),'show'=>$isSuper],
 ['label'=>'APIs','icon'=>'api','url'=>route('api-clients.index'),'active'=>request()->routeIs('api-clients.*'),'show'=>$user->hasPermission('api_clients.manage')],
 ['label'=>'Settings','icon'=>'settings','url'=>route('settings.index'),'active'=>request()->routeIs('settings.*')||request()->routeIs('configuration.*'),'show'=>$user->hasPermission('settings.manage')],
 ['label'=>'Audit Logs','icon'=>'audit','url'=>route('audit.index'),'active'=>request()->routeIs('audit.*'),'show'=>$user->hasPermission('audit.view')],
];
@endphp
<div class="erp-shell">
    <aside class="erp-sidebar" id="erpSidebar">
        <div class="erp-brand-wrap">
            <a class="erp-brand" href="{{ route('dashboard') }}">
                @if(!empty($brandLogoUrl))
                    <img src="{{ $brandLogoUrl }}" alt="{{ $brandName }}">
                @else
                    <span class="erp-wordmark"><b>Scratch</b><strong>gard</strong><sup>®</sup></span>
                    <small>PPF OPERATIONS ERP</small>
                @endif
            </a>
            <button class="sidebar-close" id="sidebarClose" type="button" aria-label="Close menu"><x-icon name="close" /></button>
        </div>
        <nav class="erp-side-nav" aria-label="Primary navigation">
            @foreach($nav as $item)
                @if($item['show'])
                    <a class="erp-nav-link {{ $item['active']?'active':'' }}" href="{{ $item['url'] }}">
                        <span class="erp-nav-icon"><x-icon :name="$item['icon']" /></span><span>{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
            @foreach($pluginNavigation??[] as $item)
                <a class="erp-nav-link" href="{{ $item['url'] }}"><span class="erp-nav-icon"><x-icon name="plugins" /></span><span>{{ $item['label'] }}</span></a>
            @endforeach
        </nav>
        <div class="erp-sidebar-foot">
            <div class="erp-promise-card">
                <span class="erp-promise-icon"><x-icon name="verifier" /></span>
                <strong>Protection<br>Drives Confidence</strong>
                <small>Vehicles · People · Quality</small>
            </div>
            <div class="erp-sidebar-version">Scratchgard ERP <span>v2.8.0</span></div>
        </div>
    </aside>
    <div class="erp-mobile-backdrop" id="sidebarBackdrop"></div>

    <section class="erp-main-shell">
        <header class="erp-topbar">
            <div class="erp-topbar-left">
                <button class="erp-mobile-menu" id="sidebarOpen" type="button" aria-label="Open menu"><x-icon name="menu" /></button>
                <form class="erp-global-search" method="get" action="{{ route('work-orders.index') }}">
                    <x-icon name="search" />
                    <input name="q" value="{{ request('q') }}" placeholder="Search work orders, VIN, registration, showrooms…" autocomplete="off">
                    <kbd>⌘ K</kbd>
                </form>
            </div>
            <div class="erp-topbar-actions">
                <a class="erp-icon-action" href="{{ $user->hasPermission('tickets.view')?route('tickets.index'):route('dashboard') }}" title="Notifications / tickets"><x-icon name="bell" /><span class="notify-dot"></span></a>
                <details class="erp-quick">
                    <summary><x-icon name="plus" /> Quick Actions <span>⌄</span></summary>
                    <div class="erp-quick-menu">
                        @if($user->hasPermission('work.create'))<a href="{{ route('work-orders.create') }}"><x-icon name="work" />Create work order</a>@endif
                        @if($user->hasPermission('tickets.view'))<a href="{{ route('tickets.index') }}"><x-icon name="tickets" />Open tickets</a>@endif
                        @if($isSuper)<a href="{{ route('plugins.builder') }}"><x-icon name="plugins" />Plugin Builder</a>@endif
                        <a href="{{ route('profile.edit') }}"><x-icon name="user" />My profile</a>
                    </div>
                </details>
                <div class="erp-account">
                    <a href="{{ route('profile.edit') }}" class="erp-avatar">{{ strtoupper(substr($user->name,0,1)).strtoupper(substr(strrchr(' '.$user->name,' '),1,1)) }}</a>
                    <a class="erp-account-copy" href="{{ route('profile.edit') }}"><strong>{{ $user->name }}</strong><small>{{ $user->role?->name ?? 'User' }}</small></a>
                    <form method="post" action="{{ route('logout') }}">@csrf<button class="erp-logout" type="submit" title="Logout"><x-icon name="logout" /></button></form>
                </div>
            </div>
        </header>
        <div class="plugin-slot">{!! app(\App\Plugins\PluginRuntime::class)->renderSlot('layout.after_topbar',['route'=>request()->route()?->getName()],$user) !!}</div>
        <div class="plugin-slot">{!! app(\App\Plugins\PluginRuntime::class)->renderSlot('layout.before_content',['route'=>request()->route()?->getName()],$user) !!}</div>
        <main class="erp-content">
            @if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="alert error"><strong>Please fix:</strong><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
        <div class="plugin-slot">{!! app(\App\Plugins\PluginRuntime::class)->renderSlot('layout.after_content',['route'=>request()->route()?->getName()],$user) !!}</div>
        <div class="plugin-slot">{!! app(\App\Plugins\PluginRuntime::class)->renderSlot('layout.before_footer',['route'=>request()->route()?->getName()],$user) !!}</div>
        <footer class="erp-footer"><span>© {{ date('Y') }} {{ $brandTheme['footer'] ?? 'Scratchgard PPF Operations ERP' }}</span><span>Secure field operations · Web/PWA</span></footer>
    </section>
</div>
@else
<header class="guest-topbar"><div class="guest-topbar-inner"><a class="guest-brand" href="{{ route('home') }}">@if(!empty($brandLogoUrl))<img src="{{ $brandLogoUrl }}" alt="{{ $brandName }}">@else<span class="erp-wordmark"><b>Scratch</b><strong>gard</strong><sup>®</sup></span>@endif</a><a class="btn secondary" href="{{ route('login') }}">Sign in</a></div></header>
<div class="plugin-slot">{!! app(\App\Plugins\PluginRuntime::class)->renderSlot('layout.before_content',['route'=>request()->route()?->getName()],null) !!}</div>
<main class="container">@if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif @if($errors->any())<div class="alert error"><strong>Please fix:</strong><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif @yield('content')</main>
<footer class="site-footer"><div>{{ $brandTheme['footer'] ?? 'Scratchgard PPF Operations' }}</div><small>Secure field operations · Web/PWA</small></footer>
@endauth
<script>
if('serviceWorker' in navigator){navigator.serviceWorker.register('/sw.js').catch(()=>{});}
const side=document.getElementById('erpSidebar'), openSide=document.getElementById('sidebarOpen'), closeSide=document.getElementById('sidebarClose'), backdrop=document.getElementById('sidebarBackdrop');
function setSide(v){side?.classList.toggle('open',v);backdrop?.classList.toggle('open',v);document.body.classList.toggle('no-scroll',v)}
openSide?.addEventListener('click',()=>setSide(true));closeSide?.addEventListener('click',()=>setSide(false));backdrop?.addEventListener('click',()=>setSide(false));
document.addEventListener('keydown',e=>{if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='k'){e.preventDefault();document.querySelector('.erp-global-search input')?.focus();}if(e.key==='Escape')setSide(false)});
</script>
@stack('scripts')
</body></html>
