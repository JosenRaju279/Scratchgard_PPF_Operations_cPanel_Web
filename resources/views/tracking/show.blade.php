<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="{{ $brandTheme['theme'] ?? '#0B1727' }}">
<link rel="stylesheet" href="/assets/app.css"><title>Track {{ $workOrder->work_order_number }} · {{ $brandName ?? 'Scratchgard' }}</title>
<style>:root{--brand-bg:{{ $brandTheme['theme'] ?? '#0B1727' }};--accent:{{ $brandTheme['accent'] ?? '#27B7E8' }};--text:{{ $brandTheme['text'] ?? '#EDF7FF' }};--app-font:{{ $brandTheme['font'] ?? 'Inter,ui-sans-serif,system-ui,sans-serif' }};--base-size:{{ $brandTheme['base'] ?? 16 }}px;--heading-scale:{{ $brandTheme['heading'] ?? 1.22 }};}</style>
</head>
<body>
<header class="topbar"><div class="topbar-inner"><div class="brand">@if(!empty($brandLogoUrl))<img src="{{ $brandLogoUrl }}" alt="{{ $brandName }}">@else<span class="brand-mark">S</span>@endif<span>{{ $brandName ?? 'Scratchgard' }}</span></div></div></header>
<main class="container">
<div class="page-head"><div><p class="eyebrow">Public work tracker</p><h1>{{ $workOrder->work_order_number }}</h1><p class="muted">Read-only live status. This link does not expose private evidence, chat, KYC or payment data.</p></div><span class="badge large">{{ str_replace('_',' ',$workOrder->status) }}</span></div>
<div class="grid">
<div class="col-7 card"><h2>Vehicle & job</h2><div class="detail-grid"><div><span class="muted">Vehicle</span><strong>{{ trim(($workOrder->vehicle?->make ?? '').' '.($workOrder->vehicle?->model ?? '').' '.($workOrder->vehicle?->variant ?? '')) ?: '—' }}</strong></div><div><span class="muted">Registration</span><strong>{{ $workOrder->vehicle?->registration_number ?: '—' }}</strong></div><div><span class="muted">VIN</span><strong>{{ $workOrder->vehicle?->vin ? substr($workOrder->vehicle->vin,0,4).'••••••'.substr($workOrder->vehicle->vin,-4) : '—' }}</strong></div><div><span class="muted">Package</span><strong>{{ $workOrder->package_code }}</strong></div></div></div>
<div class="col-5 card"><h2>Location</h2><strong>{{ $workOrder->showroom?->name ?? '—' }}</strong><p>{{ $workOrder->showroom?->vendor?->name }}</p><p class="muted">{{ $workOrder->showroom?->city }} {{ $workOrder->showroom?->district }} {{ $workOrder->showroom?->state }}</p>@if($workOrder->scheduled_at)<p><span class="muted">Scheduled</span><br><strong>{{ $workOrder->scheduled_at }}</strong></p>@endif</div>
</div>
<div class="card"><h2>Progress timeline</h2><div class="timeline">@forelse($workOrder->events->sortBy('created_at') as $event)<div class="timeline-item"><span class="timeline-dot"></span><div><strong>{{ ucwords(strtolower(str_replace('_',' ',$event->event_type))) }}</strong><div class="muted">{{ optional($event->created_at)->format('d M Y, h:i A') }} @if($event->to_status) · {{ str_replace('_',' ',$event->to_status) }}@endif</div></div></div>@empty<p class="muted">No progress events yet.</p>@endforelse</div></div>
<div class="alert warning">For privacy and security, photos, internal notes, contact information, approval OTPs, tickets and payment details are never shown on the public tracker.</div>
</main><footer class="site-footer"><div>{{ $brandTheme['footer'] ?? 'Scratchgard PPF Operations' }}</div><small>Public tracking · Read only</small></footer>
</body></html>
