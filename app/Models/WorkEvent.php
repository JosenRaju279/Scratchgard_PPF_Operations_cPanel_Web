<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WorkEvent extends Model {
    public $timestamps=false;
    protected $fillable=['work_order_id','actor_id','event_type','from_status','to_status','reason_id','message','metadata','created_at'];
    public function workOrder(){ return $this->belongsTo(WorkOrder::class); }
    public function actor(){ return $this->belongsTo(User::class,'actor_id'); }
    protected function casts(): array { return ['metadata'=>'array','created_at'=>'datetime']; }
}
