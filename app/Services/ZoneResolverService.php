<?php
namespace App\Services;

use App\Models\Pincode;
use App\Models\ZoneCoverageRule;
use App\Models\ZonePinAssignment;
use Illuminate\Support\Collection;

class ZoneResolverService
{
    public function resolvePincode(string $pincode): array
    {
        $pincode=trim($pincode);
        $postal=Pincode::where('pincode',$pincode)->orderBy('id')->get();
        if($postal->isEmpty()) return ['pincode'=>$pincode,'postal'=>null,'matches'=>[]];

        $matches=collect();

        // Canonical v2.1 mapping: exact PIN membership. This remains stable even if a state/district name changes later.
        foreach(ZonePinAssignment::with('zone')->where('pincode',$pincode)->whereHas('zone',fn($q)=>$q->where('status','active'))->get() as $a){
            $matches->put($a->zone_id,[
                'zone_id'=>$a->zone_id,'code'=>$a->zone?->code,'name'=>$a->zone?->name,
                'match_level'=>'pincode','specificity'=>500,'priority'=>100,
                'location'=>$this->locationFromPostal($postal->first(),$pincode),
            ]);
        }

        // Backward compatibility for installations upgraded from earlier zone-rule builds.
        if($matches->isEmpty()){
            foreach($postal as $row){
                $rules=ZoneCoverageRule::with('zone')->where('active',true)->whereHas('zone',fn($q)=>$q->where('status','active'))
                    ->where(function($q) use($row,$pincode){
                        $q->where(fn($x)=>$x->where('scope_type','pincode')->where('pincode',$pincode))
                          ->orWhere(fn($x)=>$x->where('scope_type','city')->where('state',$row->state)->where('district',$row->district)->where('city',$row->locality))
                          ->orWhere(fn($x)=>$x->where('scope_type','district')->where('state',$row->state)->where('district',$row->district))
                          ->orWhere(fn($x)=>$x->where('scope_type','state')->where('state',$row->state));
                    })->get();
                foreach($rules as $rule){
                    $specificity=match($rule->scope_type){'pincode'=>400,'city'=>300,'district'=>200,default=>100};
                    $candidate=['zone_id'=>$rule->zone_id,'code'=>$rule->zone?->code,'name'=>$rule->zone?->name,'match_level'=>'legacy_'.$rule->scope_type,'specificity'=>$specificity,'priority'=>$rule->priority,'location'=>$this->locationFromPostal($row,$pincode)];
                    $existing=$matches->get($rule->zone_id);
                    if(!$existing || [$specificity,$rule->priority] > [$existing['specificity'],$existing['priority']]) $matches->put($rule->zone_id,$candidate);
                }
            }
        }

        // Old zone_pincodes pivot compatibility.
        foreach($postal as $row){
            foreach($row->zones()->where('zones.status','active')->get() as $zone){
                if(!$matches->has($zone->id)) $matches->put($zone->id,[
                    'zone_id'=>$zone->id,'code'=>$zone->code,'name'=>$zone->name,'match_level'=>'legacy_pincode',
                    'specificity'=>390,'priority'=>90,'location'=>$this->locationFromPostal($row,$pincode)
                ]);
            }
        }

        $sorted=$matches->values()->sortByDesc(fn($m)=>sprintf('%03d-%06d',$m['specificity'],$m['priority']))->values();
        $first=$postal->first();
        return [
            'pincode'=>$pincode,
            'postal'=>['city'=>$first->locality,'district'=>$first->district,'state'=>$first->state,'office_name'=>$first->office_name],
            'matches'=>$sorted->all(),
        ];
    }

    /** Return exact distinct PINs represented by a UI selection. */
    public function pinsForSelection(array $rule): Collection
    {
        $scope=$rule['scope_type'];
        $q=Pincode::query();
        if($scope==='state') $q->where('state',$rule['state']);
        elseif($scope==='district') $q->where('state',$rule['state'])->where('district',$rule['district']);
        elseif($scope==='city') $q->where('state',$rule['state'])->where('district',$rule['district'])->where('locality',$rule['city']);
        else $q->where('pincode',$rule['pincode']);
        return $q->whereNotNull('pincode')->select('pincode')->distinct()->orderBy('pincode')->pluck('pincode');
    }

    /** Return overlapping zones based on actual PIN intersection, never name matching. */
    public function overlapsForRule(array $rule): Collection
    {
        $pins=$this->pinsForSelection($rule);
        if($pins->isEmpty()) return collect();
        return ZonePinAssignment::with('zone')->whereIn('pincode',$pins)->get()
            ->groupBy('zone_id')->map(function($rows){
                $first=$rows->first();
                return (object)['zone_id'=>$first->zone_id,'zone'=>$first->zone,'scope_type'=>'pincode','overlap_count'=>$rows->pluck('pincode')->unique()->count()];
            })->values();
    }

    private function locationFromPostal($row,string $pin): array
    {
        return ['pincode'=>$pin,'city'=>$row?->locality,'district'=>$row?->district,'state'=>$row?->state];
    }
}
