@extends('layouts.app')
@section('title','Audit Trail')
@section('content')
<h1>Operational Audit Trail</h1>
<div class="card"><table><thead><tr><th>Time</th><th>Work</th><th>Actor</th><th>Event</th><th>State</th><th>Message</th></tr></thead><tbody>
@foreach($events as $e)<tr><td>{{ optional($e->created_at)->format('d M Y H:i:s') }}</td><td><a href="{{ route('work-orders.show',$e->work_order_id) }}">{{ $e->workOrder?->work_order_number }}</a></td><td>{{ $e->actor?->name ?? '#'.$e->actor_id }}</td><td>{{ $e->event_type }}</td><td>{{ $e->from_status }} → {{ $e->to_status }}</td><td>{{ $e->message }}</td></tr>@endforeach
</tbody></table>{{ $events->links() }}</div>
@endsection
