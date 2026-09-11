<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Zone extends Model {
    protected $fillable=['name','code','state','status'];
    public function pincodes(){return $this->belongsToMany(Pincode::class,'zone_pincodes');}
    public function users(){return $this->belongsToMany(User::class,'user_zones')->withTimestamps()->withPivot(['is_primary','assigned_by','source']);}
    public function coverageRules(){return $this->hasMany(ZoneCoverageRule::class);}
    public function mappingBatches(){return $this->hasMany(ZoneMappingBatch::class);}
    public function pinAssignments(){return $this->hasMany(ZonePinAssignment::class);}
    public function showrooms(){return $this->hasMany(Showroom::class);}
}
