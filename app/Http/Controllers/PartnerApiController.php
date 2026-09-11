<?php
namespace App\Http\Controllers;

use App\Models\Showroom;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Illuminate\Http\Request;

class PartnerApiController extends Controller
{
    public function store(Request $request, WorkOrderService $service)
    {
        $client=$request->attributes->get('api_client');abort_unless($client->can('work.create'),403,'Client cannot create work.');
        $v=$request->validate(['vin'=>'required|string|max:64','registration_number'=>'nullable|string|max:40','make'=>'required|string|max:80','model'=>'required|string|max:80','variant'=>'nullable|string|max:80','color'=>'nullable|string|max:50','showroom_code'=>'required|string|max:80','package_code'=>'required|string|max:80','job_type'=>'nullable|string|max:80','external_reference'=>'required|string|max:120','scheduled_at'=>'nullable|date','notes'=>'nullable|string|max:3000']);
        $idem=$request->header('Idempotency-Key') ?: $v['external_reference'];$source='partner:'.$client->client_id.':'.hash('sha256',$idem);
        if($existing=WorkOrder::where('source_system',$source)->first())return response()->json($this->resource($existing),200);
        $showroom=Showroom::where('code',strtoupper($v['showroom_code']))->where('status','active')->firstOrFail();
        $vehicle=Vehicle::firstOrCreate(['vin'=>strtoupper(trim($v['vin']))],['registration_number'=>$v['registration_number'] ?? null,'make'=>$v['make'],'model'=>$v['model'],'variant'=>$v['variant'] ?? null,'color'=>$v['color'] ?? null]);
        $work=$service->create(['vehicle_id'=>$vehicle->id,'showroom_id'=>$showroom->id,'vendor_organization_id'=>$showroom->vendor_organization_id,'zone_id'=>$showroom->zone_id,'package_code'=>strtoupper($v['package_code']),'job_type'=>$v['job_type'] ?? null,'external_reference'=>$v['external_reference'],'scheduled_at'=>$v['scheduled_at'] ?? null,'notes'=>$v['notes'] ?? null,'creation_source'=>'api','source_system'=>$source,'zone_code'=>$showroom->zone?->code ?? 'GEN'],\App\Models\User::whereHas('role',fn($q)=>$q->where('slug','super_admin'))->orderBy('id')->firstOrFail());
        return response()->json($this->resource($work),201);
    }
    public function show(Request $request,string $workOrderNumber)
    {
        $client=$request->attributes->get('api_client');abort_unless($client->can('work.read'),403);$work=WorkOrder::with(['vehicle','showroom','zone','applicator'])->where('work_order_number',$workOrderNumber)->where('source_system','like','partner:'.$client->client_id.':%')->firstOrFail();return response()->json($this->resource($work));
    }
    public function byExternal(Request $request,string $externalReference)
    {
        $client=$request->attributes->get('api_client');abort_unless($client->can('work.read'),403);$work=WorkOrder::with(['vehicle','showroom','zone','applicator'])->where('external_reference',$externalReference)->where('source_system','like','partner:'.$client->client_id.':%')->latest()->firstOrFail();return response()->json($this->resource($work));
    }
    private function resource(WorkOrder $w): array{$w->loadMissing(['vehicle','showroom','zone','applicator']);return ['work_order_number'=>$w->work_order_number,'external_reference'=>$w->external_reference,'status'=>$w->status,'vin'=>$w->vehicle?->vin,'registration_number'=>$w->vehicle?->registration_number,'vehicle'=>trim(($w->vehicle?->make ?? '').' '.($w->vehicle?->model ?? '')),'showroom_code'=>$w->showroom?->code,'showroom'=>$w->showroom?->name,'zone'=>$w->zone?->code,'applicator'=>$w->applicator?->name,'scheduled_at'=>optional($w->scheduled_at)->toIso8601String(),'approved_at'=>optional($w->approved_at)->toIso8601String(),'public_tracking_url'=>($w->tracking_enabled&&$w->tracking_token)?route('tracking.show',$w->tracking_token):null,'created_at'=>$w->created_at?->toIso8601String()];}
}
