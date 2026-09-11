<?php
namespace App\Http\Controllers;

use App\Models\WorkOrder;
use Illuminate\Http\Request;

class PublicTrackingController extends Controller
{
    public function show(Request $request, string $token)
    {
        $safeEvents=[
            'WORK_CREATED','WORK_DELEGATED','APPLICATOR_ASSIGNED','APPLICATOR_REASSIGNED','WORK_STARTED',
            'WORK_SUBMITTED','WORK_APPROVED','RETURN','REJECT','QUALITY_HOLD','REWORK_CREATED','REWORK_STATUS_CHANGED'
        ];
        $work=WorkOrder::with([
            'vehicle','showroom.vendor','zone',
            'events'=>fn($q)=>$q->whereIn('event_type',$safeEvents)->orderBy('created_at'),
        ])->where('tracking_token',$token)->where('tracking_enabled',true)->firstOrFail();
        return view('tracking.show',['workOrder'=>$work]);
    }
}
