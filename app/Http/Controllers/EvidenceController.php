<?php
namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\EvidenceItem;
use App\Models\WorkOrder;
use App\Models\User;
use App\Services\EvidenceStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Plugins\PluginRuntime;

class EvidenceController extends Controller
{
    public function index(Request $request)
    {
        $u=auth()->user();$role=$u->role?->slug;
        $q=EvidenceItem::with(['workOrder.vehicle','workOrder.showroom','user'])->whereNull('deleted_at')->latest();
        if($role==='zonal_manager'){
            $zoneIds=$u->zones()->pluck('zones.id')->push($u->primary_zone_id)->filter()->unique();
            $q->whereHas('workOrder',fn($w)=>$w->where('zonal_manager_id',$u->id)->orWhereIn('zone_id',$zoneIds));
        }elseif($role==='applicator')$q->where('user_id',$u->id);
        elseif($role==='external_verifier')$q->whereHas('workOrder',fn($w)=>$w->where('external_verifier_id',$u->id));
        elseif(!in_array($role,['super_admin','work_delegator','finance'],true) && !$u->hasPermission('work.override'))abort(403);
        if($request->filled('stage'))$q->where('stage',$request->input('stage'));
        if($request->filled('q')){$term=trim((string)$request->input('q'));$q->whereHas('workOrder',fn($w)=>$w->where('work_order_number','like','%'.$term.'%')->orWhereHas('vehicle',fn($v)=>$v->where('vin','like','%'.$term.'%')));}
        return view('evidence.index',['items'=>$q->paginate(36)->withQueryString()]);
    }

    public function store(Request $request, WorkOrder $workOrder, EvidenceStorageService $storage)
    {
        abort_unless(auth()->id()===$workOrder->applicator_id || auth()->user()->hasPermission('work.override'),403);
        $v=$request->validate([
            'photo'=>'required|image|max:12288',
            'stage'=>'required|in:BEFORE,DURING,AFTER,REWORK_BEFORE,REWORK_AFTER',
            'slot_code'=>'required|string|max:100',
            'vehicle_area'=>'nullable|string|max:120',
            'latitude'=>'nullable|numeric|between:-90,90',
            'longitude'=>'nullable|numeric|between:-180,180',
            'gps_accuracy'=>'nullable|numeric|min:0',
        ]);
        $storage->store($workOrder,auth()->user(),$request->file('photo'),$v);
        return back()->with('status','Evidence uploaded.');
    }

    public function checkin(Request $request, WorkOrder $workOrder)
    {
        abort_unless(auth()->id()===$workOrder->applicator_id || auth()->user()->hasPermission('work.override'),403);
        $v=$request->validate([
            'type'=>'required|in:START,END,REWORK_START,REWORK_END',
            'latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180',
            'accuracy'=>'nullable|numeric|min:0','exception_notes'=>'nullable|string|max:1000'
        ]);
        $distance=null;$inside=null;
        if($workOrder->showroom && $workOrder->showroom->latitude!==null && $workOrder->showroom->longitude!==null){
            $distance=$this->distanceMeters((float)$v['latitude'],(float)$v['longitude'],(float)$workOrder->showroom->latitude,(float)$workOrder->showroom->longitude);
            $inside=$distance <= (float)($workOrder->showroom->geofence_radius_m ?: 250);
        }
        $checkin=Checkin::create([
            'work_order_id'=>$workOrder->id,'user_id'=>auth()->id(),'type'=>$v['type'],'latitude'=>$v['latitude'],
            'longitude'=>$v['longitude'],'accuracy'=>$v['accuracy'] ?? null,'distance_from_showroom_m'=>$distance,
            'within_geofence'=>$inside,'exception_notes'=>$v['exception_notes'] ?? null,'checked_at'=>now()
        ]);
        app(PluginRuntime::class)->doAction('location.checkin',$checkin->fresh(),$workOrder,auth()->user());
        return back()->with('status',$inside===false?'Location captured outside geofence; exception may require review.':'Location check-in saved.');
    }

    public function view(EvidenceItem $evidence)
    {
        $u=auth()->user();
        abort_unless($u,403);
        $work=WorkOrder::findOrFail($evidence->work_order_id);
        $role=$u->role?->slug;
        $allowed=$role==='super_admin' || $role==='work_delegator' || $role==='finance'
            || ($role==='zonal_manager' && ($work->zonal_manager_id===$u->id || $u->zones()->where('zones.id',$work->zone_id)->exists()))
            || ($role==='applicator' && $work->applicator_id===$u->id)
            || ($role==='external_verifier' && $work->external_verifier_id===$u->id);
        abort_unless($allowed,403);
        if($evidence->disk==='s3'){
            try{return redirect()->away(Storage::disk('s3')->temporaryUrl($evidence->path,now()->addMinutes(10)));}
            catch(\Throwable $e){abort(404,'Evidence object is unavailable.');}
        }
        return Storage::disk($evidence->disk)->response($evidence->path);
    }

    private function distanceMeters(float $lat1,float $lon1,float $lat2,float $lon2): float
    {
        $r=6371000;
        $p1=deg2rad($lat1);$p2=deg2rad($lat2);
        $dp=deg2rad($lat2-$lat1);$dl=deg2rad($lon2-$lon1);
        $a=sin($dp/2)**2+cos($p1)*cos($p2)*sin($dl/2)**2;
        return $r*2*atan2(sqrt($a),sqrt(1-$a));
    }
}
