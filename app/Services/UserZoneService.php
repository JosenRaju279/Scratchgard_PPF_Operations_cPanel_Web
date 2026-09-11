<?php
namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UserZoneService
{
    public function assign(User $user, array $zoneIds, ?User $actor=null, string $source='admin', ?int $primaryZoneId=null, bool $forceMultiple=false): void
    {
        $zoneIds=array_values(array_unique(array_map('intval',$zoneIds)));
        if(!$zoneIds) throw new RuntimeException('At least one zone is required.');
        $active=Zone::whereIn('id',$zoneIds)->where('status','active')->pluck('id')->all();
        if(count($active)!==count($zoneIds)) throw new RuntimeException('One or more selected zones are not active.');

        $globalMulti=(bool)Setting::getValue('allow_multiple_zones_default',false);
        $allowedMultiple=$forceMultiple || $user->allow_multiple_zones || $globalMulti;
        if(!$allowedMultiple && count($zoneIds)>1) throw new RuntimeException('This user is configured for one zone only. Enable multi-zone for this user or globally first.');

        $primaryZoneId=$primaryZoneId ?: $zoneIds[0];
        if(!in_array((int)$primaryZoneId,$zoneIds,true)) throw new RuntimeException('Primary zone must be one of the assigned zones.');

        DB::transaction(function() use($user,$zoneIds,$actor,$source,$primaryZoneId){
            $previous=$user->zones()->pluck('zones.id')->map(fn($id)=>(int)$id)->values()->all();
            $previousPrimary=$user->primary_zone_id;
            $payload=[];
            foreach($zoneIds as $id) $payload[$id]=[
                'is_primary'=>(int)$id===(int)$primaryZoneId,
                'assigned_by'=>$actor?->id,
                'source'=>$source,
                'created_at'=>now(),'updated_at'=>now(),
            ];
            $user->zones()->sync($payload);
            $user->update(['primary_zone_id'=>$primaryZoneId]);
            DB::table('user_zone_events')->insert([
                'user_id'=>$user->id,'actor_id'=>$actor?->id,'previous_zone_ids'=>json_encode($previous),
                'new_zone_ids'=>json_encode($zoneIds),'previous_primary_zone_id'=>$previousPrimary,
                'new_primary_zone_id'=>$primaryZoneId,'source'=>$source,'notes'=>null,'created_at'=>now(),
            ]);
        });
    }
}
