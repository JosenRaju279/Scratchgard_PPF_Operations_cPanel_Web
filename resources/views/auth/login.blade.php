@extends('layouts.app')
@section('title',($portal['title'] ?? 'Sign in'))
@section('content')
@php
    $portalKey = request()->route('portal');
    $attemptRoute = match($portalKey){
        'super_admin' => 'login.super-admin.attempt',
        'work_delegator' => 'login.delegator.attempt',
        'zonal_manager' => 'login.zonal-manager.attempt',
        'applicator' => 'login.applicator.attempt',
        'external_verifier' => 'login.showroom.attempt',
        'finance' => 'login.finance.attempt',
        default => 'login.attempt',
    };
@endphp
<div class="auth-shell">
    <div class="auth-card portal-auth-card">
        <div class="portal-auth-head">
            <a class="portal-back" href="{{ route('home') }}">← ERP home</a>
            <span class="portal-icon {{ $portal['accent'] ?? 'universal' }}">{{ strtoupper(substr($portal['name'] ?? 'ERP',0,1)) }}</span>
        </div>
        <p class="eyebrow">{{ $portal ? 'Role-secured access' : 'Secure operations access' }}</p>
        <h1>{{ $portal['title'] ?? 'Sign in to Scratchgard ERP' }}</h1>
        <p class="muted">{{ $portal['description'] ?? 'Use your username, verified email address, or mobile number.' }}</p>
        @if($portal)<div class="portal-lock-note">Only <strong>{{ $portal['name'] }}</strong> accounts can sign in through this portal.</div>@endif
        <form method="post" action="{{ route($attemptRoute) }}">
            @csrf
            <div class="field"><label>Username / email / mobile</label><input name="identifier" value="{{ old('identifier') }}" autocomplete="username" required></div>
            <div class="field"><label>Password</label><input type="password" name="password" autocomplete="current-password" required></div>
            <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
            <button class="btn wide">Sign in</button>
        </form>
        <div class="auth-foot">
            @if(($portalKey ?? null)==='applicator')
                <a href="{{ route('applicator.register') }}">New Applicator? Request registration</a>
            @elseif(!$portal)
                <a href="{{ route('applicator.register') }}">New Applicator? Request registration</a>
            @else
                <a href="{{ route('login') }}">Use universal sign in</a>
            @endif
        </div>
    </div>
</div>
@endsection
