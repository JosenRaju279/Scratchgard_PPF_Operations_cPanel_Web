<?php
namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\Showroom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\Zone;
use App\Services\WorkOrderService;
use App\Services\IdentityNormalizerService;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiController extends Controller
{
    public function login(Request $request, IdentityNormalizerService $identity)
    {
        $v=$request->validate(['identifier'=>'required|string|max:190','password'=>'required|string','device_name'=>'nullable|string|max:120']);
        $raw=trim($v['identifier']);$user=null;
        if(str_contains($raw,'@')){
            $email=$identity->normalizeEmail($raw);$user=$email?User::where('email',$email)->first():null;
        } elseif(preg_match('/^[+\d\s().-]+$/',$raw) && strlen(preg_replace('/\D+/','',$raw))>=10){
            try{$mobile=$identity->normalizeMobile($raw,null,(string)Setting::getValue('default_mobile_country_code','+91'))['canonical'];$user=User::where('mobile',$mobile)->first();}catch(\Throwable $ignored){}
        } else {
            $username=$identity->normalizeUsername($raw);$user=$username?User::where('username',$username)->first():null;
        }
        if(!$user || !Hash::check($v['password'],$user->password) || $user->status!=='active') return response()->json(['message'=>'Invalid credentials'],401);
        $plain=Str::random(80);
        ApiToken::create(['user_id'=>$user->id,'name'=>$v['device_name'] ?? 'api','token_hash'=>hash('sha256',$plain),'abilities'=>['*'],'expires_at'=>now()->addDays(30)]);
        return response()->json(['token'=>$plain,'token_type'=>'Bearer','expires_in_days'=>30,'user'=>['id'=>$user->id,'username'=>$user->username,'name'=>$user->name]]);
    }

    public function me(Request $request){ return response()->json($request->user()->load(['role','zones'])); }

    public function index(Request $request)
    {
        $u=$request->user(); $q=WorkOrder::with(['vehicle','showroom','zone'])->latest();
        if($u->role?->slug==='applicator')$q->where('applicator_id',$u->id);
        if($u->role?->slug==='zonal_manager')$q->where('zonal_manager_id',$u->id);
        return response()->json($q->paginate(25));
    }

    public function store(Request $request, WorkOrderService $service)
    {
        abort_unless($request->user()->hasPermission('work.create'),403);
        $v=$request->validate([
            'vin'=>'required|string|max:64','registration_number'=>'nullable|string|max:40','make'=>'required|string|max:80','model'=>'required|string|max:80',
            'showroom_code'=>'required|string|max:80','package_code'=>'required|string|max:80','external_reference'=>'nullable|string|max:120',
            'scheduled_at'=>'nullable|date','idempotency_key'=>'nullable|string|max:190'
        ]);
        if(!empty($v['idempotency_key'])){
            $existing=WorkOrder::where('source_system','api:'.$v['idempotency_key'])->first();
            if($existing)return response()->json($existing,200);
        }
        $showroom=Showroom::where('code',$v['showroom_code'])->firstOrFail();
        $zone=$showroom->zone ?: Zone::findOrFail($showroom->zone_id);
        $vehicle=Vehicle::firstOrCreate(['vin'=>$v['vin']],['registration_number'=>$v['registration_number'] ?? null,'make'=>$v['make'],'model'=>$v['model']]);
        $work=$service->create([
            'vehicle_id'=>$vehicle->id,'showroom_id'=>$showroom->id,'vendor_organization_id'=>$showroom->vendor_organization_id,'zone_id'=>$zone->id,
            'package_code'=>$v['package_code'],'external_reference'=>$v['external_reference'] ?? null,'scheduled_at'=>$v['scheduled_at'] ?? null,
            'creation_source'=>'api','source_system'=>!empty($v['idempotency_key'])?'api:'.$v['idempotency_key']:'api','zone_code'=>$zone->code
        ],$request->user());
        return response()->json(['work_order'=>$work,'public_tracking_url'=>($work->tracking_enabled&&$work->tracking_token)?route('tracking.show',$work->tracking_token):null],201);
    }
}
