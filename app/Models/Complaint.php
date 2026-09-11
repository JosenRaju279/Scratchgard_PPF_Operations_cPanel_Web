<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Complaint extends Model {
    protected $fillable=['ticket_id','work_order_id','raised_by','description','affected_panels','severity','responsibility_status','status','assigned_applicator_id','reason_id','closed_at'];
    protected function casts(): array {return ['affected_panels'=>'array','closed_at'=>'datetime'];}
    public function workOrder(){return $this->belongsTo(WorkOrder::class);} public function ticket(){return $this->belongsTo(Ticket::class);} public function reworks(){return $this->hasMany(Rework::class);}
}
