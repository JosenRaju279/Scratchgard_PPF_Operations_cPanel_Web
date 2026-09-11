<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ZonePinAssignment extends Model {
    protected $fillable=['zone_id','pincode'];
    public function zone(){return $this->belongsTo(Zone::class);}
    public function sources(){return $this->hasMany(ZonePinAssignmentSource::class,'assignment_id');}
}
