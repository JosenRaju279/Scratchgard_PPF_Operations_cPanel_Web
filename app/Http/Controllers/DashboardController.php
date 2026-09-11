<?php
namespace App\Http\Controllers;

use App\Models\ApiClient;
use App\Models\KycRecord;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\Plugin;
use App\Models\Showroom;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Zone;
use App\Plugins\PluginRuntime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user=auth()->user();
        $query=$this->scopedWorkQuery($user);
        $role=$user->role?->slug;

        $statusGroups=[
            'new'=>['NEW','DELEGATED'],
            'assigned'=>['APPLICATOR_ASSIGNED','APPLICATOR_ACCEPTED'],
            'in_progress'=>['AT_LOCATION','BEFORE_EVIDENCE_COMPLETE','WORK_IN_PROGRESS'],
            'pending_review'=>['WORK_SUBMITTED','AWAITING_EXTERNAL_REVIEW'],
            'approved'=>['APPROVED','PAYMENT_ELIGIBLE','PAID','CLOSED'],
            'rework'=>['CORRECTION_REQUIRED','REWORK_REQUIRED','QUALITY_HOLD'],
        ];
        $stats=['total'=>(clone $query)->count()];
        foreach($statusGroups as $key=>$statuses)$stats[$key]=(clone $query)->whereIn('status',$statuses)->count();

        $activeApplicators=User::whereHas('role',fn($q)=>$q->where('slug','applicator'))->where('status','active');
        if($role==='zonal_manager'){
            $zoneIds=$user->zones()->pluck('zones.id')->push($user->primary_zone_id)->filter()->unique()->values();
            $activeApplicators->where(function($q)use($zoneIds){$q->whereIn('primary_zone_id',$zoneIds)->orWhereHas('zones',fn($z)=>$z->whereIn('zones.id',$zoneIds));});
        }
        $stats['active_applicators']=$activeApplicators->count();
        $stats['active_zones']=$role==='zonal_manager' ? $user->zones()->where('zones.status','active')->count() : Zone::where('status','active')->count();
        $stats['open_tickets']=$user->hasPermission('tickets.view') ? Ticket::whereNotIn('status',['closed','resolved'])->count() : 0;
        $stats['active_showrooms']=Showroom::where('status','active')->count();

        $paymentTotal=Payment::count();
        $paymentPaid=Payment::where('status','paid')->count();
        $stats['payment_percent']=$paymentTotal ? (int)round(($paymentPaid/$paymentTotal)*100) : 100;

        $recent=(clone $query)->with(['vehicle','showroom','applicator','payment'])->latest()->limit(8)->get();

        $applicatorWorkloads=collect();
        if(in_array($role,['zonal_manager','super_admin','work_delegator'],true)){
            $w=DB::table('work_orders')->join('users','users.id','=','work_orders.applicator_id')->whereNotIn('work_orders.status',['CLOSED','REJECTED','CANCELLED']);
            if($role==='zonal_manager')$w->where('work_orders.zonal_manager_id',$user->id);
            $applicatorWorkloads=$w->groupBy('work_orders.applicator_id','users.name','users.pincode')
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->selectRaw("work_orders.applicator_id, users.name, users.pincode, COUNT(*) open_count, SUM(CASE WHEN work_orders.status IN ('WORK_IN_PROGRESS','AT_LOCATION','BEFORE_EVIDENCE_COMPLETE') THEN 1 ELSE 0 END) active_count, SUM(CASE WHEN work_orders.status IN ('WORK_SUBMITTED','AWAITING_EXTERNAL_REVIEW') THEN 1 ELSE 0 END) review_count, SUM(CASE WHEN work_orders.status IN ('CORRECTION_REQUIRED','REWORK_REQUIRED','QUALITY_HOLD') THEN 1 ELSE 0 END) rework_count")
                ->limit(8)->get();
        }

        $start=now()->startOfDay()->subDays(6);
        $weeklyRows=(clone $query)->where(function($q)use($start){
            $q->where('created_at','>=',$start)->orWhere('approved_at','>=',$start)
              ->orWhere(function($r)use($start){$r->whereIn('status',['CORRECTION_REQUIRED','REWORK_REQUIRED','QUALITY_HOLD'])->where('updated_at','>=',$start);});
        })->get(['created_at','approved_at','updated_at','status']);
        $weekly=[];
        for($i=0;$i<7;$i++){
            $day=$start->copy()->addDays($i);$key=$day->toDateString();
            $weekly[$key]=['label'=>$day->format('M j'),'new'=>0,'approved'=>0,'rework'=>0];
        }
        foreach($weeklyRows as $row){
            if($row->created_at && isset($weekly[$row->created_at->toDateString()]))$weekly[$row->created_at->toDateString()]['new']++;
            if($row->approved_at && isset($weekly[$row->approved_at->toDateString()]))$weekly[$row->approved_at->toDateString()]['approved']++;
            if(in_array($row->status,['CORRECTION_REQUIRED','REWORK_REQUIRED','QUALITY_HOLD'],true) && $row->updated_at && isset($weekly[$row->updated_at->toDateString()]))$weekly[$row->updated_at->toDateString()]['rework']++;
        }
        $weekly=array_values($weekly);
        $weeklyMax=max(1,...array_map(fn($d)=>$d['new']+$d['approved']+$d['rework'],$weekly));

        $statusSplit=[];$splitTotal=max(1,$stats['total']);
        foreach([
            ['New','new','#2f80ed'],['Assigned','assigned','#69a7ff'],['In Progress','in_progress','#f0b429'],
            ['Awaiting Review','pending_review','#8b5cf6'],['Approved','approved','#2fb97f'],['Rework','rework','#ee5d6c']
        ] as [$label,$key,$color]){
            $count=$stats[$key];$statusSplit[]=['label'=>$label,'key'=>$key,'count'=>$count,'percent'=>(int)round(($count/$splitTotal)*100),'color'=>$color];
        }

        $zonePerformance=collect();
        if(in_array($role,['super_admin','work_delegator','zonal_manager'],true)){
            $zoneQuery=Zone::where('status','active');
            if($role==='zonal_manager'){
                $zoneIds=$user->zones()->pluck('zones.id')->push($user->primary_zone_id)->filter()->unique();
                $zoneQuery->whereIn('id',$zoneIds);
            }
            $zones=$zoneQuery->orderBy('name')->get();
            $ids=$zones->pluck('id');
            $counts=DB::table('work_orders')->whereIn('zone_id',$ids)->groupBy('zone_id')->selectRaw("zone_id, COUNT(*) total_count, SUM(CASE WHEN status NOT IN ('CLOSED','REJECTED','CANCELLED') THEN 1 ELSE 0 END) open_count, SUM(CASE WHEN status IN ('APPROVED','PAYMENT_ELIGIBLE','PAID','CLOSED') THEN 1 ELSE 0 END) approved_count, SUM(CASE WHEN status IN ('CORRECTION_REQUIRED','REWORK_REQUIRED','QUALITY_HOLD') THEN 1 ELSE 0 END) rework_count")->get()->keyBy('zone_id');
            $managers=User::whereHas('role',fn($q)=>$q->where('slug','zonal_manager'))->whereIn('primary_zone_id',$ids)->orderBy('name')->get()->groupBy('primary_zone_id');
            $zonePerformance=$zones->map(function($zone)use($counts,$managers){$c=$counts->get($zone->id);$open=(int)($c->open_count??0);$total=(int)($c->total_count??0);return ['id'=>$zone->id,'name'=>$zone->name,'code'=>$zone->code,'manager'=>$managers->get($zone->id)?->first()?->name ?? 'Unassigned','open'=>$open,'approved'=>(int)($c->approved_count??0),'rework'=>(int)($c->rework_count??0),'load'=>min(100,$total?max(8,(int)round(($open/max(1,$total))*100)):0)];})->sortByDesc('open')->take(6)->values();
        }

        $pendingActions=[];
        if($user->hasPermission('kyc.review'))$pendingActions[]=['icon'=>'kyc','label'=>'KYC approvals','count'=>KycRecord::whereIn('status',['submitted','under_review','resubmission_required'])->count(),'url'=>route('kyc.index'),'action'=>'Review'];
        $pendingActions[]=['icon'=>'verifier','label'=>'Verifier approvals','count'=>$stats['pending_review'],'url'=>route('work-orders.index'),'action'=>'Review'];
        if($role==='super_admin')$pendingActions[]=['icon'=>'plugins','label'=>'Plugin health','count'=>Plugin::whereNotNull('last_error')->count(),'url'=>route('plugins.index'),'action'=>'Manage'];
        if($user->hasPermission('payment.view'))$pendingActions[]=['icon'=>'payments','label'=>'Payment review items','count'=>Payment::whereIn('status',['eligible','held'])->count(),'url'=>route('payments.index'),'action'=>'Review'];
        if($user->hasPermission('tickets.view'))$pendingActions[]=['icon'=>'tickets','label'=>'Open tickets','count'=>$stats['open_tickets'],'url'=>route('tickets.index'),'action'=>'Open'];

        $pluginSummary=['enabled'=>0,'total'=>0,'failed'=>0];
        if($role==='super_admin'){$pluginSummary=['enabled'=>Plugin::where('enabled',true)->count(),'total'=>Plugin::count(),'failed'=>Plugin::whereNotNull('last_error')->count()];}
        $mailSince=now()->subDays(7);$mailSent=NotificationLog::where('created_at','>=',$mailSince)->where('status','sent')->count();$mailFailed=NotificationLog::where('created_at','>=',$mailSince)->where('status','failed')->count();$mailTotal=$mailSent+$mailFailed;
        $mailSummary=['sent'=>$mailSent,'failed'=>$mailFailed,'rate'=>$mailTotal?(float)round(($mailSent/$mailTotal)*100,1):100.0];
        $systemSummary=['tracking_enabled'=>(clone $query)->where('tracking_enabled',true)->count(),'api_active'=>$user->hasPermission('api_clients.manage')?ApiClient::where('active',true)->count():null];

        $pluginWidgets=app(PluginRuntime::class)->widgetsFor($user);
        return view('dashboard.index',compact('stats','recent','user','applicatorWorkloads','pluginWidgets','weekly','weeklyMax','statusSplit','zonePerformance','pendingActions','pluginSummary','mailSummary','systemSummary'));
    }

    private function scopedWorkQuery(User $user): Builder
    {
        $query=WorkOrder::query();$role=$user->role?->slug;
        if($role==='zonal_manager')$query->where(function($q)use($user){$zoneIds=$user->zones()->pluck('zones.id')->push($user->primary_zone_id)->filter()->unique();$q->where('zonal_manager_id',$user->id)->orWhereIn('zone_id',$zoneIds);});
        if($role==='applicator')$query->where('applicator_id',$user->id);
        if($role==='external_verifier')$query->where('external_verifier_id',$user->id);
        return $query;
    }
}
