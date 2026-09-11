<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Payment extends Model {
    protected $fillable=['work_order_id','eligible_amount','currency','status','paid_amount','paid_at','mode','reference','recorded_by','previous_status','held_at','hold_reason_id','reverted_at','reverted_by','revert_reason_id','revert_notes'];
    public function workOrder(){return $this->belongsTo(WorkOrder::class);} public function recordedBy(){return $this->belongsTo(User::class,'recorded_by');}
    protected function casts(): array {return ['eligible_amount'=>'decimal:2','paid_amount'=>'decimal:2','paid_at'=>'datetime','held_at'=>'datetime','reverted_at'=>'datetime'];}
}
