<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Ticket extends Model {
    protected $fillable=['ticket_number','work_order_id','raised_by','assigned_to','category','subject','description','priority','status','source','last_activity_at','closed_at'];
    protected function casts(): array { return ['last_activity_at'=>'datetime','closed_at'=>'datetime']; }
    public function workOrder(){ return $this->belongsTo(WorkOrder::class); }
    public function raiser(){ return $this->belongsTo(User::class,'raised_by'); }
    public function assignee(){ return $this->belongsTo(User::class,'assigned_to'); }
    public function participants(){ return $this->belongsToMany(User::class,'ticket_participants')->withTimestamps()->withPivot('participant_role'); }
    public function messages(){ return $this->hasMany(TicketMessage::class); }
    public function events(){ return $this->hasMany(TicketEvent::class); }
}
