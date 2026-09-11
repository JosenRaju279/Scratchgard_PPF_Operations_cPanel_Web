<?php
namespace App\Http\Controllers;

use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function store(Request $request, WorkOrder $workOrder)
    {
        $v=$request->validate(['message'=>'required|string|max:5000']);
        $u=auth()->user();
        $role=$u->role?->slug;
        $allowed=in_array($role,['super_admin','work_delegator'],true)
            || in_array($u->id,array_filter([$workOrder->zonal_manager_id,$workOrder->applicator_id,$workOrder->external_verifier_id]),true)
            || ($role==='zonal_manager' && $u->zones()->where('zones.id',$workOrder->zone_id)->exists());
        abort_unless($allowed,403);
        DB::table('messages')->insert(['work_order_id'=>$workOrder->id,'sender_id'=>$u->id,'recipient_id'=>null,'message'=>$v['message'],'metadata'=>null,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('status','Message added to work thread.');
    }
}
