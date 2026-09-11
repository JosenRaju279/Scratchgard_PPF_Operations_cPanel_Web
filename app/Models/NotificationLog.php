<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NotificationLog extends Model {
    protected $fillable=['user_id','work_order_id','event_type','email','subject','status','error','metadata','sent_at'];
    protected function casts(): array { return ['metadata'=>'array','sent_at'=>'datetime']; }
    public function user(){ return $this->belongsTo(User::class); }
    public function workOrder(){ return $this->belongsTo(WorkOrder::class); }
}
