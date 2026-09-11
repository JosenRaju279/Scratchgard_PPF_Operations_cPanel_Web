<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Rework extends Model {
    protected $fillable=['complaint_id','work_order_id','reference','assigned_applicator_id','status','reason_id','corrective_action','film_quantity','film_unit','submitted_at','reviewed_by','reviewed_at','review_decision','scope_classification','payment_treatment','scope_changed_by','scope_changed_at','scope_notes','linked_new_work_order_id'];
    protected function casts(): array {return ['film_quantity'=>'decimal:2','submitted_at'=>'datetime','reviewed_at'=>'datetime','scope_changed_at'=>'datetime'];}
    public function workOrder(){return $this->belongsTo(WorkOrder::class);} public function complaint(){return $this->belongsTo(Complaint::class);} public function applicator(){return $this->belongsTo(User::class,'assigned_applicator_id');} public function linkedNewWorkOrder(){return $this->belongsTo(WorkOrder::class,'linked_new_work_order_id');}
}
