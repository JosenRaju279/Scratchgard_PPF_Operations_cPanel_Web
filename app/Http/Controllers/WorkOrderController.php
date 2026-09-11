<?php
namespace App\Http\Controllers;

use App\Models\Reason;
use App\Models\EvidenceTemplate;
use App\Models\Role;
use App\Models\Showroom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\Zone;
use App\Services\WorkOrderService;
use App\Services\IdentityVerificationService;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        $q=WorkOrder::with(['vehicle','showroom','zone','applicator'])->latest();
        if($request->filled('vin')) $q->whereHas('vehicle',fn($x)=>$x->where('vin','like','%'.$request->input('vin').'%'));
        if($request->filled('q')){
            $term=trim((string)$request->input('q'));
            $q->where(function($x)use($term){
                $x->where('work_order_number','like','%'.$term.'%')->orWhere('external_reference','like','%'.$term.'%')
                  ->orWhereHas('vehicle',fn($v)=>$v->where('vin','like','%'.$term.'%')->orWhere('registration_number','like','%'.$term.'%')->orWhere('make','like','%'.$term.'%')->orWhere('model','like','%'.$term.'%'))
                  ->orWhereHas('showroom',fn($s)=>$s->where('name','like','%'.$term.'%')->orWhere('code','like','%'.$term.'%'));
            });
        }
        $u=auth()->user();
        if ($u->role?->slug==='zonal_manager') $q->where('zonal_manager_id',$u->id);
        if ($u->role?->slug==='applicator') $q->where('applicator_id',$u->id);
        if ($u->role?->slug==='external_verifier') $q->where('external_verifier_id',$u->id);
        $workOrders=$q->paginate(25)->withQueryString();
        return view('work-orders.index',compact('workOrders'));
    }

    public function create()
    {
        $zones=Zone::where('status','active')->orderBy('name')->get();
        $showrooms=Showroom::where('status','active')->with('vendor')->orderBy('name')->get();
        return view('work-orders.create',compact('zones','showrooms'));
    }

    public function store(Request $request, WorkOrderService $service)
    {
        $v=$request->validate([
            'vin'=>'required|string|max:64',
            'registration_number'=>'nullable|string|max:40',
            'make'=>'required|string|max:80','model'=>'required|string|max:80','variant'=>'nullable|string|max:80','color'=>'nullable|string|max:50',
            'showroom_id'=>'required|exists:showrooms,id','zone_id'=>'required|exists:zones,id',
            'package_code'=>'required|string|max:80','job_type'=>'nullable|string|max:80','scheduled_at'=>'nullable|date',
            'external_reference'=>'nullable|string|max:120','pricing_amount'=>'nullable|numeric|min:0','notes'=>'nullable|string|max:3000'
        ]);
        $vehicle=Vehicle::firstOrCreate(['vin'=>$v['vin']],[
            'registration_number'=>$v['registration_number'] ?? null,'make'=>$v['make'],'model'=>$v['model'],
            'variant'=>$v['variant'] ?? null,'color'=>$v['color'] ?? null
        ]);
        $zone=Zone::findOrFail($v['zone_id']);
        $showroom=Showroom::findOrFail($v['showroom_id']);
        $work=$service->create([
            'vehicle_id'=>$vehicle->id,'showroom_id'=>$showroom->id,'vendor_organization_id'=>$showroom->vendor_organization_id,
            'zone_id'=>$zone->id,'package_code'=>$v['package_code'],'job_type'=>$v['job_type'] ?? null,
            'scheduled_at'=>$v['scheduled_at'] ?? null,'external_reference'=>$v['external_reference'] ?? null,
            'pricing_amount'=>$v['pricing_amount'] ?? null,'pricing_currency'=>'INR','notes'=>$v['notes'] ?? null,'zone_code'=>$zone->code,
        ],auth()->user(),(array)$request->input('plugin_fields',[]));
        return redirect()->route('work-orders.show',$work)->with('status','Work order created.');
    }

    public function show(WorkOrder $workOrder)
    {
        $this->authorizeAccess($workOrder);
        $workOrder->load(['vehicle','showroom.vendor','zone','zonalManager','applicator','verifier','assignments','evidence','checkins','events']);
        $messages=DB::table('messages')->leftJoin('users','users.id','=','messages.sender_id')->where('work_order_id',$workOrder->id)
            ->orderBy('messages.created_at')->select('messages.*','users.name as sender_name')->get();
        $complaints=\App\Models\Complaint::where('work_order_id',$workOrder->id)->with(['workOrder','ticket','reworks.applicator','reworks.linkedNewWorkOrder'])->latest()->get();
        $payment=\App\Models\Payment::where('work_order_id',$workOrder->id)->first();
        $applicators=User::whereHas('role',fn($q)=>$q->where('slug','applicator'))->where('status','active')->where(function($q) use($workOrder){$q->where('primary_zone_id',$workOrder->zone_id)->orWhereHas('zones',fn($z)=>$z->where('zones.id',$workOrder->zone_id));})->withCount(['applicatorWorkOrders as open_work_count'=>fn($q)=>$q->whereNotIn('status',['CLOSED','REJECTED','CANCELLED'])])->orderBy('name')->get();
        $zonalManagers=User::whereHas('role',fn($q)=>$q->where('slug','zonal_manager'))->where('status','active')->orderBy('name')->get();
        $verifiers=User::with('showroom')->whereHas('role',fn($q)=>$q->where('slug','external_verifier'))->where('status','active')->whereNotNull('email')->where(function($q)use($workOrder){$q->whereNull('showroom_id')->orWhere('showroom_id',$workOrder->showroom_id);})->orderBy('name')->get();
        $reasons=Reason::where('active',1)->orderBy('category')->orderBy('sort_order')->get();
        $evidenceTemplate=EvidenceTemplate::with('slots')->where('package_code',$workOrder->package_code)->where('active',true)->orderByDesc('version')->first();
        return view('work-orders.show',compact('workOrder','applicators','zonalManagers','verifiers','reasons','messages','complaints','payment','evidenceTemplate'));
    }

    public function delegate(Request $request, WorkOrder $workOrder, WorkOrderService $service)
    {
        $this->authorizeManagerAccess($workOrder);
        $v=$request->validate(['zonal_manager_id'=>'required|exists:users,id','notes'=>'nullable|string|max:1000']);
        $manager=User::findOrFail($v['zonal_manager_id']);
        abort_unless($manager->role?->slug==='zonal_manager',422,'Selected user is not a Zonal Manager.');
        $from=$workOrder->status;
        $workOrder->update(['zonal_manager_id'=>$manager->id,'status'=>'DELEGATED']);
        $service->event($workOrder,auth()->user(),'WORK_DELEGATED',$from,'DELEGATED',null,$v['notes'] ?? null,['zonal_manager_id'=>$manager->id]);
        return back()->with('status','Work delegated to Zonal Manager.');
    }

    public function assign(Request $request, WorkOrder $workOrder, WorkOrderService $service)
    {
        $this->authorizeManagerAccess($workOrder);
        $v=$request->validate(['applicator_id'=>'required|exists:users,id','reason_id'=>'nullable|exists:reasons,id','notes'=>'nullable|string|max:1000']);
        $applicator=User::findOrFail($v['applicator_id']);
        abort_unless($applicator->role?->slug==='applicator',422,'Selected user is not an Applicator.');
        $reason=!empty($v['reason_id'])?Reason::find($v['reason_id']):null;
        $service->assignApplicator($workOrder,$applicator,auth()->user(),$reason,$v['notes'] ?? null);
        return back()->with('status','Applicator assignment updated.');
    }

    public function assignVerifier(Request $request, WorkOrder $workOrder, WorkOrderService $service)
    {
        $this->authorizeManagerAccess($workOrder);
        $v=$request->validate(['external_verifier_id'=>'required|exists:users,id']);
        $verifier=User::findOrFail($v['external_verifier_id']);
        abort_unless($verifier->role?->slug==='external_verifier',422,'Selected user is not an External Verifier.');
        abort_unless($verifier->email,422,'External verifier must have an email address because review confirmation uses email OTP.');
        if($verifier->showroom_id) abort_unless((int)$verifier->showroom_id===(int)$workOrder->showroom_id,422,'This verifier profile belongs to a different showroom.');
        $workOrder->update(['external_verifier_id'=>$verifier->id]);
        $service->event($workOrder,auth()->user(),'VERIFIER_ASSIGNED',$workOrder->status,$workOrder->status,null,'Verifier assigned',['external_verifier_id'=>$verifier->id]);
        return back()->with('status','Verifier assigned.');
    }

    public function start(WorkOrder $workOrder, WorkOrderService $service)
    {
        $this->authorizeAccess($workOrder);
        abort_unless(auth()->id()===$workOrder->applicator_id || auth()->user()->hasPermission('work.override'),403);
        $service->transition($workOrder,auth()->user(),'WORK_IN_PROGRESS','WORK_STARTED');
        return back()->with('status','Work started.');
    }

    public function submit(WorkOrder $workOrder, WorkOrderService $service)
    {
        $this->authorizeAccess($workOrder);
        abort_unless(auth()->id()===$workOrder->applicator_id || auth()->user()->hasPermission('work.override'),403);
        $template=EvidenceTemplate::with('slots')->where('package_code',$workOrder->package_code)->where('active',true)->orderByDesc('version')->first();
        if($template){
            $mandatory=$template->slots->where('mandatory',true);
            $done=$workOrder->evidence()->whereNull('deleted_at')->get()->map(fn($e)=>$e->stage.'|'.$e->slot_code)->all();
            $missing=[];
            foreach($mandatory as $slot){
                if(!in_array($slot->stage.'|'.$slot->slot_code,$done,true)) $missing[]=$slot->stage.': '.$slot->label;
            }
            abort_if($missing,422,'Mandatory evidence missing: '.implode('; ',$missing));
        } else {
            $before=$workOrder->evidence()->where('stage','BEFORE')->count();
            $after=$workOrder->evidence()->where('stage','AFTER')->count();
            abort_if($before<1 || $after<1,422,'Before and after evidence is required before submission.');
        }
        abort_if(!$workOrder->checkins()->where('type','START')->exists() || !$workOrder->checkins()->where('type','END')->exists(),422,'Start and end location check-ins are required.');
        $workOrder->update(['submitted_at'=>now()]);
        $service->transition($workOrder,auth()->user(),'AWAITING_EXTERNAL_REVIEW','WORK_SUBMITTED');
        return back()->with('status','Work submitted for verification.');
    }

    public function reviewOtp(WorkOrder $workOrder, IdentityVerificationService $verification)
    {
        $this->authorizeAccess($workOrder);
        $u=auth()->user();
        abort_unless($u->role?->slug==='external_verifier' && (int)$workOrder->external_verifier_id===(int)$u->id,403);
        abort_unless($u->email,422,'Your verifier profile has no email address. Ask Scratchgard to update it.');
        abort_unless(in_array($workOrder->status,['WORK_SUBMITTED','AWAITING_EXTERNAL_REVIEW','QUALITY_HOLD','CORRECTION_REQUIRED'],true),422,'This work is not currently in a reviewable state.');
        $verification->issue($u,'email',$u->email,'work_review:'.$workOrder->id);
        return back()->with('status','Review OTP sent to '.$u->email.'.');
    }

    public function review(Request $request, WorkOrder $workOrder, WorkOrderService $service)
    {
        $this->authorizeAccess($workOrder);
        $v=$request->validate(['decision'=>'required|in:approve,return,reject,quality_hold','reason_id'=>'nullable|exists:reasons,id','comments'=>'nullable|string|max:2000','email_otp'=>'nullable|digits:6']);
        $u=auth()->user();
        $reviewVerification=null;
        if($u->role?->slug==='external_verifier' && $u->approval_otp_required){
            abort_unless((int)$workOrder->external_verifier_id===(int)$u->id,403);
            abort_unless($u->email,422,'Verifier email is required for OTP-confirmed review.');
            if(empty($v['email_otp'])) return back()->withErrors(['email_otp'=>'Enter the OTP sent to your registered email before confirming this review.']);
            $reviewVerification=app(IdentityVerificationService::class)->verifyRow($u,'email',$v['email_otp'],'work_review:'.$workOrder->id);
            if(!$reviewVerification) return back()->withErrors(['email_otp'=>'Review OTP is invalid or expired. Send a fresh OTP and try again.']);
        }
        if ($v['decision']==='approve') {
            if($u->role?->slug!=='super_admin'){
                abort_unless(in_array($workOrder->status,['WORK_SUBMITTED','AWAITING_EXTERNAL_REVIEW','QUALITY_HOLD'],true),422,'Work is not currently awaiting approval.');
            }
            abort_unless($u->role?->slug==='super_admin' || $u->role?->slug==='external_verifier' || $u->hasPermission('work.approve'),403);
            $reason=!empty($v['reason_id'])?Reason::find($v['reason_id']):null;
            $service->approve($workOrder,$u,$reason,$v['comments'] ?? null);
        } else {
            abort_unless($u->role?->slug==='super_admin' || $u->role?->slug==='external_verifier' || $u->hasPermission('work.disapprove'),403);
            $reason=!empty($v['reason_id'])?Reason::find($v['reason_id']):null;
            $to=match($v['decision']){'return'=>'CORRECTION_REQUIRED','reject'=>'REJECTED','quality_hold'=>'QUALITY_HOLD'};
            $service->transition($workOrder,$u,$to,strtoupper($v['decision']),$reason,$v['comments'] ?? null);
        }
        \App\Models\Review::create([
            'work_order_id'=>$workOrder->id,'reviewer_id'=>$u->id,'reviewer_role'=>$u->role?->slug ?? 'unknown',
            'decision'=>$v['decision'],'reason_id'=>$v['reason_id'] ?? null,'comments'=>$v['comments'] ?? null,
            'verification_method'=>$reviewVerification?'email_otp':null,'identity_verification_id'=>$reviewVerification?->id,'created_at'=>now()
        ]);
        return back()->with('status','Review recorded.');
    }

    public function regenerateTracking(WorkOrder $workOrder)
    {
        $this->authorizeManagerAccess($workOrder);
        abort_unless(auth()->user()->role?->slug==='super_admin' || auth()->user()->hasPermission('work.override'),403);
        $workOrder->update(['tracking_token'=>hash('sha256',Str::uuid()->toString().Str::random(48).microtime(true)),'tracking_enabled'=>true]);
        return back()->with('status','Public tracking URL regenerated. Previous tracker URL is no longer valid.');
    }

    public function toggleTracking(Request $request, WorkOrder $workOrder)
    {
        $this->authorizeManagerAccess($workOrder);
        abort_unless(auth()->user()->role?->slug==='super_admin' || auth()->user()->hasPermission('work.override'),403);
        $workOrder->update(['tracking_enabled'=>$request->boolean('tracking_enabled')]);
        return back()->with('status','Public tracking '.($workOrder->tracking_enabled?'enabled':'disabled').'.');
    }

    private function authorizeAccess(WorkOrder $work): void
    {
        $u=auth()->user();
        abort_unless($u,403);
        $role=$u->role?->slug;
        if(in_array($role,['super_admin','work_delegator','finance'],true)) return;
        if($role==='zonal_manager'){
            $zoneIds=$u->zones()->pluck('zones.id')->all();
            abort_unless($work->zonal_manager_id===$u->id || in_array($work->zone_id,$zoneIds,true),403);
            return;
        }
        if($role==='applicator'){ abort_unless($work->applicator_id===$u->id,403); return; }
        if($role==='external_verifier'){ abort_unless($work->external_verifier_id===$u->id,403); return; }
        abort(403);
    }

    private function authorizeManagerAccess(WorkOrder $work): void
    {
        $u=auth()->user();
        if($u->role?->slug==='super_admin' || $u->role?->slug==='work_delegator') return;
        if($u->role?->slug==='zonal_manager'){
            abort_unless($work->zonal_manager_id===$u->id || $u->zones()->where('zones.id',$work->zone_id)->exists(),403);
            return;
        }
        abort(403);
    }

}
