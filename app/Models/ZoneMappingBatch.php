<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ZoneMappingBatch extends Model {
    protected $fillable=['zone_id','source_scope','source_state','source_district','source_city','source_pincode','pincode_count','created_by'];
    public function zone(){return $this->belongsTo(Zone::class);}
    public function sources(){return $this->hasMany(ZonePinAssignmentSource::class,'batch_id');}
}
