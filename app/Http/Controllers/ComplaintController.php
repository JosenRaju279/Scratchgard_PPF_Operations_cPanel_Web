<?php
namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Payment;
use App\Models\Reason;
use App\Models\Rework;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\TicketService;
use App\Services\WorkOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplaintController extends Controller
{
    public function index(){return view('complaints.index',['complaints'=>Complaint::with(['workOrder','ticket','reworks'])->latest()->paginate(30)]);}

    public function store(Request $request, WorkOrder $workOrder, WorkOrderService $service, TicketService $tickets)
    {
        abort_unless(auth()->user()->role?->slug==='super_admin'||auth()->user()->hasPermission('complaints.manage'),403);
        $v=$request->validate(['description'=>'required|string|max:3000','affected_panels'=>'nullable|string|max:1000','severity'=>'required|in:low,medium,high,critical','responsibility_status'=>'nullable|string|max:120','reason_id'=>'nullable|exists:reasons,id','assigned_applicator_id'=>'nullable|exists:users,id']);
        return DB::transaction(function()use($v,$workOrder,$service,$tickets){
            $participantIds=array_filter([$workOrder->zonal_manager_id,$workOrder->applicator_id,$workOrder->external_verifier_id,$v['assigned_applicator_id'] ?? null]);
            $ticket=$tickets->create(['work_order_id'=>$workOrder->id,'assigned_to'=>$workOrder->zonal_manager_id,'category'=>'complaint','subject'=>'Complaint — '.$workOrder->work_order_number,'description'=>$v['description'],'priority'=>$v['severity']==='critical'?'critical':($v['severity']==='high'?'high':'normal'),'status'=>'open','source'=>'complaint'],auth()->user(),$participantIds);
            $c=Complaint::create(['ticket_id'=>$ticket->id,'work_order_id'=>$workOrder->id,'raised_by'=>auth()->id(),'description'=>$v['description'],'affected_panels'=>array_values(array_filter(array_map('trim',explode(',',$v['affected_panels'] ?? '')))),'severity'=>$v['severity'],'responsibility_status'=>$v['responsibility_status'] ?? 'under_review','status'=>'open','assigned_applicator_id'=>$v['assigned_applicator_id'] ?? $workOrder->applicator_id,'reason_id'=>$v['reason_id'] ?? null]);
            $workOrder->evidence()->whereNull('protected_reason')->update(['protected_reason'=>'complaint:'.$c->id]);
            $payment=Payment::where('work_order_id',$workOrder->id)->first();
            if($payment && $payment->status==='eligible')$payment->update(['previous_status'=>'eligible','status'=>'held','held_at'=>now(),'hold_reason_id'=>$v['reason_id'] ?? null]);
            $service->event($workOrder,auth()->user(),'COMPLAINT_OPENED',$workOrder->status,$workOrder->status,null,$v['description'],['complaint_id'=>$c->id,'ticket_id'=>$ticket->id,'payment_status'=>$payment?->fresh()->status]);
            return back()->with('status','Complaint and ticket opened. Paid payments are preserved; unpaid eligible payment is held pending resolution.');
        });
    }

    public function reworkStore(Request $request, Complaint $complaint, WorkOrderService $workService)
    {
        abort_unless(auth()->user()->role?->slug==='super_admin'||auth()->user()->hasPermission('complaints.manage'),403);
        $v=$request->validate(['assigned_applicator_id'=>'required|exists:users,id','reason_id'=>'nullable|exists:reasons,id','corrective_action'=>'nullable|string|max:3000','film_quantity'=>'nullable|numeric|min:0','film_unit'=>'nullable|string|max:20']);
        $n=Rework::where('work_order_id',$complaint->work_order_id)->count()+1;$work=$complaint->workOrder;
        $r=Rework::create(['complaint_id'=>$complaint->id,'work_order_id'=>$work->id,'reference'=>$work->work_order_number.'-R'.$n,'assigned_applicator_id'=>$v['assigned_applicator_id'],'status'=>'assigned','reason_id'=>$v['reason_id'] ?? null,'corrective_action'=>$v['corrective_action'] ?? null,'film_quantity'=>$v['film_quantity'] ?? null,'film_unit'=>$v['film_unit'] ?? null,'scope_classification'=>'in_scope_rework','payment_treatment'=>'no_new_payment']);
        $complaint->update(['assigned_applicator_id'=>$v['assigned_applicator_id'],'status'=>'rework']);
        if($complaint->ticket)$complaint->ticket->participants()->syncWithoutDetaching([$v['assigned_applicator_id']=>['participant_role'=>'rework_applicator']]);
        $workService->event($work,auth()->user(),'REWORK_CREATED',$work->status,$work->status,$v['reason_id']?Reason::find($v['reason_id']):null,$v['corrective_action'] ?? 'Rework assigned',['rework_id'=>$r->id,'assigned_applicator_id'=>$v['assigned_applicator_id']]);
        return back()->with('status','Rework created: '.$r->reference.'. Original payment record is unchanged.');
    }

    public function reworkUpdate(Request $request, Rework $rework, WorkOrderService $workService)
    {
        abort_unless(auth()->user()->role?->slug==='super_admin'||auth()->user()->hasPermission('complaints.manage'),403);
        $v=$request->validate(['status'=>'required|in:assigned,in_progress,submitted,approved,returned,closed','review_decision'=>'nullable|string|max:80','corrective_action'=>'nullable|string|max:3000','film_quantity'=>'nullable|numeric|min:0','film_unit'=>'nullable|string|max:20','assigned_applicator_id'=>'nullable|exists:users,id']);
        $from=$rework->status;$rework->update([...$v,'reviewed_by'=>in_array($v['status'],['approved','returned','closed'])?auth()->id():$rework->reviewed_by,'reviewed_at'=>in_array($v['status'],['approved','returned','closed'])?now():$rework->reviewed_at]);
        $workService->event($rework->workOrder,auth()->user(),'REWORK_STATUS_CHANGED',$rework->workOrder->status,$rework->workOrder->status,null,'Rework '.$rework->reference.' changed '.$from.' → '.$v['status'],['rework_id'=>$rework->id,'rework_status'=>$v['status']]);
        return back()->with('status','Rework updated.');
    }

    public function changeScope(Request $request, Rework $rework, WorkOrderService $workService)
    {
        abort_unless(auth()->user()->role?->slug==='super_admin'||auth()->user()->hasPermission('work.change_rework_scope'),403);
        $v=$request->validate(['scope_classification'=>'required|in:in_scope_rework,out_of_scope_new_work','notes'=>'required|string|max:3000','applicator_id'=>'nullable|exists:users,id']);
        if($v['scope_classification']==='in_scope_rework'){$rework->update(['scope_classification'=>'in_scope_rework','payment_treatment'=>'no_new_payment','scope_changed_by'=>auth()->id(),'scope_changed_at'=>now(),'scope_notes'=>$v['notes']]);return back()->with('status','Rework retained inside original scope. Original payment history remains untouched.');}
        if($rework->linked_new_work_order_id)return back()->withErrors(['scope_classification'=>'A linked out-of-scope work order already exists for this rework.']);
        $parent=$rework->workOrder->load(['vehicle','showroom','zone']);
        $new=$workService->create(['vehicle_id'=>$parent->vehicle_id,'showroom_id'=>$parent->showroom_id,'vendor_organization_id'=>$parent->vendor_organization_id,'zone_id'=>$parent->zone_id,'zonal_manager_id'=>$parent->zonal_manager_id,'package_code'=>$parent->package_code,'job_type'=>'OUT_OF_SCOPE_REWORK','creation_source'=>'rework_scope_conversion','source_system'=>'internal','parent_work_order_id'=>$parent->id,'origin_rework_id'=>$rework->id,'relationship_type'=>'out_of_scope_followup','notes'=>'Created from '.$rework->reference.': '.$v['notes'],'zone_code'=>$parent->zone?->code ?? 'GEN'],auth()->user());
        if(!empty($v['applicator_id'])){$a=User::findOrFail($v['applicator_id']);$new->update(['status'=>'DELEGATED']);$workService->assignApplicator($new,$a,auth()->user(),null,'Assigned while converting out-of-scope rework to new work.');}
        $rework->update(['scope_classification'=>'out_of_scope_new_work','payment_treatment'=>'new_work_payment','scope_changed_by'=>auth()->id(),'scope_changed_at'=>now(),'scope_notes'=>$v['notes'],'linked_new_work_order_id'=>$new->id]);
        $workService->event($parent,auth()->user(),'REWORK_SCOPE_CHANGED_TO_NEW_WORK',$parent->status,$parent->status,null,$v['notes'],['rework_id'=>$rework->id,'linked_new_work_order_id'=>$new->id]);
        return back()->with('status','Rework moved outside original scope and linked new Work Order '.$new->work_order_number.' created. Original paid/approved work remains historically intact.');
    }

    public function status(Request $request, Complaint $complaint, WorkOrderService $workService)
    {
        abort_unless(auth()->user()->role?->slug==='super_admin'||auth()->user()->hasPermission('complaints.manage'),403);$v=$request->validate(['status'=>'required|in:open,under_review,rework,closed,reopened']);$complaint->update(['status'=>$v['status'],'closed_at'=>$v['status']==='closed'?now():null]);
        if($v['status']==='closed'){$days=(int)\App\Models\Setting::getValue('evidence_retention_days',365);$complaint->workOrder->evidence()->where('protected_reason','complaint:'.$complaint->id)->update(['protected_reason'=>null,'retention_until'=>now()->addDays($days)]);$p=$complaint->workOrder->payment;if($p&&$p->status==='held')$p->update(['previous_status'=>'held','status'=>'eligible','held_at'=>null,'hold_reason_id'=>null]);}
        elseif(in_array($v['status'],['open','reopened','under_review','rework'],true))$complaint->workOrder->evidence()->whereNull('protected_reason')->update(['protected_reason'=>'complaint:'.$complaint->id]);
        $workService->event($complaint->workOrder,auth()->user(),'COMPLAINT_STATUS_CHANGED',$complaint->workOrder->status,$complaint->workOrder->status,null,'Complaint #'.$complaint->id.' status: '.$v['status'],['complaint_id'=>$complaint->id,'complaint_status'=>$v['status']]);
        return back()->with('status','Complaint status updated.');
    }
}
