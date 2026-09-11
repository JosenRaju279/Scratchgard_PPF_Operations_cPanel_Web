<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EvidenceItem extends Model {
    protected $fillable=['work_order_id','user_id','stage','vehicle_area','slot_code','disk','path','preview_path','mime_type','size_bytes','sha256','latitude','longitude','gps_accuracy','captured_at','retention_until','protected_reason','deleted_at'];
    protected function casts(): array { return ['captured_at'=>'datetime','retention_until'=>'datetime','deleted_at'=>'datetime']; }
    public function workOrder(){return $this->belongsTo(WorkOrder::class);}
    public function user(){return $this->belongsTo(User::class);}
}
