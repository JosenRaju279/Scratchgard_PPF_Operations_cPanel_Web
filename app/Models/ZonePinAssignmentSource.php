<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ZonePinAssignmentSource extends Model {
    protected $table='zone_pin_assignment_sources';
    protected $fillable=['assignment_id','batch_id'];
    public function assignment(){return $this->belongsTo(ZonePinAssignment::class,'assignment_id');}
    public function batch(){return $this->belongsTo(ZoneMappingBatch::class,'batch_id');}
}
