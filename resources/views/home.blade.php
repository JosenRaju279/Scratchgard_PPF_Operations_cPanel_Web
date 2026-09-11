@extends('layouts.app')
@section('title',($brandName ?? 'Scratchgard').' ERP')
@section('content')
<section class="erp-hero">
    <div class="erp-hero-copy">
        <div class="erp-kicker"><span></span> PPF Operations ERP</div>
        <h1>One operating system for every Scratchgard PPF work order.</h1>
        <p>Control delegation, zones, applicators, VIN-based jobs, camera evidence, showroom verification, complaints, rework, payments, tickets and integrations from one secure web/PWA platform.</p>
        <div class="erp-hero-actions">
            @auth
                <a class="btn" href="{{ route('dashboard') }}">Open ERP Dashboard</a>
                <a class="btn secondary" href="{{ route('work-orders.index') }}">View Work Orders</a>
            @else
                <a class="btn" href="{{ route('login') }}">Universal Sign In</a>
                <a class="btn secondary" href="#portals">Choose Your Portal</a>
            @endauth
        </div>
        <div class="erp-proof-row">
            <div><strong>VIN</strong><span>Vehicle-linked history</span></div>
            <div><strong>GPS</strong><span>Location-backed execution</span></div>
            <div><strong>OTP</strong><span>Verifier confirmation</span></div>
            <div><strong>API</strong><span>Dealer-ready integrations</span></div>
        </div>
    </div>
    <div class="erp-console-card">
        <div class="erp-console-top"><span class="live-dot"></span><span>Operations snapshot</span><small>Web/PWA</small></div>
        <div class="erp-console-flow">
            <div><span>01</span><strong>Work received</strong><small>Portal / API / manual</small></div>
            <i>→</i>
            <div><span>02</span><strong>Zone delegated</strong><small>PIN-mapped operations</small></div>
            <i>→</i>
            <div><span>03</span><strong>Applicator executes</strong><small>GPS + evidence</small></div>
            <i>→</i>
            <div><span>04</span><strong>Verified</strong><small>Showroom OTP approval</small></div>
        </div>
        <div class="erp-console-grid">
            <div><small>Control model</small><strong>Role + scope</strong></div>
            <div><small>Evidence</small><strong>Camera-first</strong></div>
            <div><small>Rework</small><strong>Full history</strong></div>
            <div><small>Extensions</small><strong>Plugin framework</strong></div>
        </div>
    </div>
</section>

<section class="erp-section" id="portals">
    <div class="erp-section-head"><div><p class="eyebrow">Role gateways</p><h2>Enter the ERP through your assigned portal</h2><p class="muted">Each portal validates the account role before access. Universal sign-in remains available when needed.</p></div></div>
    <div class="portal-grid">
        @foreach($portals as $key=>$portal)
            <a class="portal-card portal-{{ $portal['accent'] }}" href="{{ route($portal['route']) }}">
                <div class="portal-card-top"><span class="portal-symbol">{{ strtoupper(substr($portal['name'],0,1)) }}</span><span class="portal-arrow">↗</span></div>
                <strong>{{ $portal['title'] }}</strong>
                <p>{{ $portal['description'] }}</p>
                <span class="portal-enter">Open portal</span>
            </a>
        @endforeach
    </div>
</section>

<section class="erp-section">
    <div class="erp-section-head"><div><p class="eyebrow">ERP modules</p><h2>Operational modules built around the work order</h2></div></div>
    <div class="erp-module-grid">
        <article><span>01</span><h3>Work & VIN</h3><p>API/manual job creation, vehicle identity, showroom, assignment and complete state history.</p></article>
        <article><span>02</span><h3>Zones & People</h3><p>PIN-first zone mapping, KYC, role permissions, applicator workload and reassignment controls.</p></article>
        <article><span>03</span><h3>Field Evidence</h3><p>Mobile-first camera capture, GPS check-ins, evidence checklists and protected retention.</p></article>
        <article><span>04</span><h3>Quality & Rework</h3><p>Showroom OTP verification, correction, complaints, tickets, rework and quality holds.</p></article>
        <article><span>05</span><h3>Payments</h3><p>Status-driven settlement, controlled reversals, historical protection and finance exports.</p></article>
        <article><span>06</span><h3>Plugins & API</h3><p>Super Admin-managed extension framework and scoped third-party dealer/ERP APIs.</p></article>
    </div>
</section>

<section class="erp-track-banner">
    <div><p class="eyebrow">Public work tracking</p><h2>Customers and partners can follow a work order from its private tracking link.</h2><p class="muted">Tracking links expose safe operational status only. VIN, evidence, payment, KYC and internal communication remain protected.</p></div>
    <div class="track-url-demo"><span>your-domain.com/track/</span><strong>secure-random-token</strong></div>
</section>
@endsection
