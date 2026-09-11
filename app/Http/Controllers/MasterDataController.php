<?php
namespace App\Http\Controllers;

use App\Models\Pincode;
use App\Models\Showroom;
use App\Models\VendorOrganization;
use App\Models\Zone;
use App\Models\ZoneCoverageRule;
use App\Models\ZoneMappingBatch;
use App\Models\ZonePinAssignment;
use App\Models\ZonePinAssignmentSource;
use App\Services\ZoneResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MasterDataController extends Controller
{
    public function index()
    {
        return view('masters.index',[
            'zones'=>Zone::withCount(['pincodes','coverageRules','pinAssignments','mappingBatches'])->orderBy('status')->orderBy('name')->get(),
            'vendors'=>VendorOrganization::with('showrooms')->orderBy('name')->get(),
            'showrooms'=>Showroom::with(['vendor','zone'])->latest()->limit(100)->get(),
            'states'=>Pincode::whereNotNull('state')->select('state')->distinct()->orderBy('state')->pluck('state'),
            'postalCount'=>Pincode::count(),
            'coverageRules'=>ZoneCoverageRule::with('zone')->latest()->limit(50)->get(),
            'mappingBatches'=>ZoneMappingBatch::with('zone')->latest()->limit(200)->get(),
        ]);
    }

    public function zoneStore(Request $request)
    {
        $v=$request->validate(['name'=>'required|string|max:120','code'=>'required|string|max:30|unique:zones,code']);
        Zone::create(['name'=>$v['name'],'code'=>strtoupper($v['code']),'status'=>'active']);
        return back()->with('status','Zone created. Add State/District/City/PIN coverage rules below.');
    }

    public function zoneUpdate(Request $request, Zone $zone)
    {
        abort_unless(auth()->user()->role?->slug==='super_admin',403,'Only Super Admin can modify zone identity/status.');
        $v=$request->validate(['name'=>'required|string|max:120','code'=>'required|string|max:30|unique:zones,code,'.$zone->id,'status'=>'required|in:active,archived']);
        $zone->update(['name'=>$v['name'],'code'=>strtoupper($v['code']),'status'=>$v['status']]);
        return back()->with('status','Zone updated.');
    }

    public function zoneDelete(Zone $zone)
    {
        abort_unless(auth()->user()->role?->slug==='super_admin',403,'Only Super Admin can archive/delete zones.');
        if($zone->users()->exists() || $zone->showrooms()->exists() || DB::table('work_orders')->where('zone_id',$zone->id)->exists()){
            $zone->update(['status'=>'archived']);
            return back()->with('status','Zone has history, so it was archived instead of physically deleted.');
        }
        $zone->delete();
        return back()->with('status','Unused zone deleted.');
    }

    public function locationOptions(Request $request)
    {
        $type=$request->query('type','state');$state=$request->query('state');$district=$request->query('district');
        $q=Pincode::query(); if($state)$q->where('state',$state); if($district)$q->where('district',$district);
        $values=match($type){
            'district'=>$q->whereNotNull('district')->select('district')->distinct()->orderBy('district')->limit(1000)->pluck('district'),
            'city'=>$q->whereNotNull('locality')->select('locality')->distinct()->orderBy('locality')->limit(2000)->pluck('locality'),
            'pincode'=>$q->select('pincode')->distinct()->orderBy('pincode')->limit(5000)->pluck('pincode'),
            default=>$q->whereNotNull('state')->select('state')->distinct()->orderBy('state')->pluck('state'),
        };
        return response()->json($values);
    }

    public function postalLookup(Request $request, ZoneResolverService $resolver)
    {
        $v=$request->validate(['pincode'=>'required|digits:6']);
        return response()->json($resolver->resolvePincode($v['pincode']));
    }

    public function coveragePreview(Request $request, ZoneResolverService $resolver)
    {
        $v=$request->validate(['scope_type'=>'required|in:state,district,city,pincode','state'=>'nullable|string|max:120','district'=>'nullable|string|max:120','city'=>'nullable|string|max:160','pincode'=>'nullable|digits:6']);
        if($v['scope_type']==='state' && empty($v['state'])) return response()->json(['zones'=>[]]);
        if($v['scope_type']==='district' && (empty($v['state'])||empty($v['district']))) return response()->json(['zones'=>[]]);
        if($v['scope_type']==='city' && (empty($v['state'])||empty($v['district'])||empty($v['city']))) return response()->json(['zones'=>[]]);
        if($v['scope_type']==='pincode' && empty($v['pincode'])) return response()->json(['zones'=>[]]);
        $pins=$resolver->pinsForSelection($v);
        $zones=$resolver->overlapsForRule($v)->map(fn($r)=>['zone_id'=>$r->zone_id,'code'=>$r->zone?->code,'name'=>$r->zone?->name,'scope_type'=>'pincode','overlap_count'=>$r->overlap_count ?? 0])->unique('zone_id')->values();
        return response()->json(['zones'=>$zones,'pincode_count'=>$pins->count()]);
    }

    public function coverageStore(Request $request, ZoneResolverService $resolver)
    {
        $v=$request->validate(['zone_id'=>'required|exists:zones,id','scope_type'=>'required|in:state,district,city,pincode','state'=>'nullable|string|max:120','district'=>'nullable|string|max:120','city'=>'nullable|string|max:160','pincode'=>'nullable|digits:6']);
        if($v['scope_type']==='state' && empty($v['state']))return back()->withErrors(['state'=>'Select a state from the postal master.']);
        if($v['scope_type']==='district' && (empty($v['state'])||empty($v['district'])))return back()->withErrors(['district'=>'Select state and district from the postal master.']);
        if($v['scope_type']==='city' && (empty($v['state'])||empty($v['district'])||empty($v['city'])))return back()->withErrors(['city'=>'Select state, district and locality from the postal master.']);
        if($v['scope_type']==='pincode' && empty($v['pincode']))return back()->withErrors(['pincode'=>'PIN code is required.']);

        // Important: State/District/City is only a selector. We persist exact PIN membership.
        // Therefore Odisha/Orissa spelling or future administrative renaming cannot silently move an existing zone.
        $pins=$resolver->pinsForSelection($v);
        if($pins->isEmpty())return back()->withErrors(['scope_type'=>'No postal PINs were found for this selection. Import/refresh the postal master first.']);

        $overlaps=ZonePinAssignment::with('zone')->whereIn('pincode',$pins)->where('zone_id','!=',$v['zone_id'])->get()
            ->groupBy('zone_id')->map(fn($rows)=>['zone'=>$rows->first()->zone,'count'=>$rows->pluck('pincode')->unique()->count()])->values();

        $duplicateBatch=ZoneMappingBatch::where('zone_id',$v['zone_id'])->where('source_scope',$v['scope_type'])
            ->where('source_state',$v['state'] ?? null)->where('source_district',$v['district'] ?? null)
            ->where('source_city',$v['city'] ?? null)->where('source_pincode',$v['pincode'] ?? null)->exists();
        if($duplicateBatch)return back()->withErrors(['scope_type'=>'This exact zone-mapping selection already exists for the selected zone.']);

        $already=ZonePinAssignment::where('zone_id',$v['zone_id'])->whereIn('pincode',$pins)->count();
        if($already===$pins->count())return back()->withErrors(['scope_type'=>'All PIN codes from this selection are already covered by this zone. No duplicate mapping was created.']);

        $created=0;
        DB::transaction(function() use($v,$pins,&$created){
            $batch=ZoneMappingBatch::create([
                'zone_id'=>$v['zone_id'],'source_scope'=>$v['scope_type'],'source_state'=>$v['state'] ?? null,'source_district'=>$v['district'] ?? null,
                'source_city'=>$v['city'] ?? null,'source_pincode'=>$v['pincode'] ?? null,'pincode_count'=>$pins->count(),'created_by'=>auth()->id(),
            ]);

            $existing=ZonePinAssignment::where('zone_id',$v['zone_id'])->whereIn('pincode',$pins)->pluck('id','pincode');
            $missing=$pins->reject(fn($pin)=>$existing->has((string)$pin))->values();
            $created=$missing->count();
            foreach($missing->chunk(1000) as $chunk){
                ZonePinAssignment::insertOrIgnore($chunk->map(fn($pin)=>['zone_id'=>$v['zone_id'],'pincode'=>(string)$pin,'created_at'=>now(),'updated_at'=>now()])->all());
            }

            $assignmentIds=ZonePinAssignment::where('zone_id',$v['zone_id'])->whereIn('pincode',$pins)->pluck('id');
            foreach($assignmentIds->chunk(1000) as $chunk){
                DB::table('zone_pin_assignment_sources')->insertOrIgnore($chunk->map(fn($id)=>['assignment_id'=>$id,'batch_id'=>$batch->id,'created_at'=>now(),'updated_at'=>now()])->all());
            }
        });

        $msg='Zone mapping processed '.$pins->count().' exact PIN code(s): '.$created.' new membership(s), '.($pins->count()-$created).' already covered.';
        if($overlaps->isNotEmpty())$msg.=' Overlap retained with '. $overlaps->map(fn($x)=>($x['zone']?->code ?? 'Zone').' ('.$x['count'].' PINs)')->implode(', ').'. Registration will show all matching zones when self-selection is enabled.';
        return back()->with('status',$msg);
    }

    public function zoneMappingDelete(ZoneMappingBatch $batch)
    {
        abort_unless(auth()->user()->role?->slug==='super_admin',403);
        $count=$batch->sources()->count();
        DB::transaction(function() use($batch){
            $assignmentIds=$batch->sources()->pluck('assignment_id')->all();
            $batch->delete(); // cascades this batch's provenance rows
            foreach($assignmentIds as $id){
                $assignment=ZonePinAssignment::find($id);
                if($assignment && !$assignment->sources()->exists())$assignment->delete();
            }
        });
        return back()->with('status',"Zone mapping batch removed ({$count} source links). PIN memberships still referenced by another mapping remain intact.");
    }

    public function coverageDelete(ZoneCoverageRule $rule)
    {
        abort_unless(auth()->user()->role?->slug==='super_admin',403);
        $rule->delete();
        return back()->with('status','Coverage rule deleted.');
    }

    public function postalImport(Request $request)
    {
        $request->validate(['csv'=>'required|file|max:51200']);
        $h=fopen($request->file('csv')->getRealPath(),'r'); $raw=fgetcsv($h) ?: [];
        $header=array_map(fn($x)=>$this->headerKey($x),$raw); $count=0; $skipped=0;
        $get=function(array $d,array $keys){foreach($keys as $k){if(isset($d[$k]) && trim((string)$d[$k])!=='')return trim((string)$d[$k]);}return null;};
        DB::beginTransaction();
        try{
            while(($row=fgetcsv($h))!==false){
                $row=array_pad($row,count($header),null); $d=array_combine($header,$row);
                $pin=$get($d,['pincode','pin','pincode6']); if(!$pin || !preg_match('/^\d{6}$/',$pin)){$skipped++;continue;}
                $office=$get($d,['officename','office','postofficename']); $state=$get($d,['statename','state']); $district=$get($d,['districtname','district']); $locality=$get($d,['taluk','city','locality','block']);
                Pincode::updateOrCreate(['pincode'=>$pin,'office_name'=>$office],[
                    'locality'=>$locality,'district'=>$district,'state'=>$state,'country'=>'India','office_name'=>$office,
                    'division_name'=>$get($d,['divisionname','division']),'region_name'=>$get($d,['regionname','region']),'circle_name'=>$get($d,['circlename','circle']),
                    'delivery_status'=>$get($d,['deliverystatus','delivery','delivery_status']),
                    'latitude'=>$this->numericOrNull($get($d,['latitude','lat'])),'longitude'=>$this->numericOrNull($get($d,['longitude','long','lng'])),
                    'source_version'=>'postal_csv_'.now()->format('Ymd'),
                ]);$count++;
                if($count%2000===0){DB::commit();DB::beginTransaction();}
            }
            DB::commit();fclose($h);
        }catch(\Throwable $e){DB::rollBack();fclose($h);throw $e;}
        return back()->with('status',"Postal master imported/updated {$count} rows; skipped {$skipped}. Zone creation now materializes exact PIN memberships, so later naming/spelling changes do not alter existing zones.");
    }

    // Legacy direct PIN-zone mapping retained for compatibility.
    public function pincodeStore(Request $request){$v=$request->validate(['zone_id'=>'required|exists:zones,id','pincode'=>'required|digits:6']);$pin=Pincode::where('pincode',$v['pincode'])->first();if(!$pin)return back()->withErrors(['pincode'=>'Import postal master first or use a PIN already present in the database.']);$pin->zones()->syncWithoutDetaching([$v['zone_id']]);return back()->with('status','Legacy exact PIN mapping added.');}
    public function pincodeImport(Request $request){return $this->postalImport($request);}

    public function vendorStore(Request $request){$v=$request->validate(['name'=>'required|string|max:160','code'=>'required|string|max:50|unique:vendor_organizations,code']);VendorOrganization::create(['name'=>$v['name'],'code'=>strtoupper($v['code']),'status'=>'active']);return back()->with('status','Vendor organisation created.');}
    public function showroomStore(Request $request, ZoneResolverService $resolver)
    {
        $v=$request->validate(['vendor_organization_id'=>'required|exists:vendor_organizations,id','zone_id'=>'nullable|exists:zones,id','name'=>'required|string|max:160','code'=>'required|string|max:60|unique:showrooms,code','address'=>'required|string|max:1000','pincode'=>'required|digits:6','city'=>'nullable|string|max:120','district'=>'nullable|string|max:120','state'=>'nullable|string|max:120','latitude'=>'nullable|numeric','longitude'=>'nullable|numeric','geofence_radius_m'=>'required|integer|min:10|max:10000']);
        $resolved=$resolver->resolvePincode($v['pincode']);$zoneId=$v['zone_id'] ?? ($resolved['matches'][0]['zone_id'] ?? null);if(!$zoneId)return back()->withErrors(['zone_id'=>'No zone resolves for this showroom PIN. Select/create a zone mapping first.']);
        Showroom::create([...$v,'zone_id'=>$zoneId,'code'=>strtoupper($v['code']),'city'=>$v['city'] ?? ($resolved['postal']['city'] ?? null),'district'=>$v['district'] ?? ($resolved['postal']['district'] ?? null),'state'=>$v['state'] ?? ($resolved['postal']['state'] ?? null),'country'=>'India','status'=>'active']);return back()->with('status','Showroom created.');
    }

    private function headerKey($v): string{return preg_replace('/[^a-z0-9]/','',strtolower(trim((string)$v)));}
    private function numericOrNull($v){return is_numeric($v)?$v:null;}
}
