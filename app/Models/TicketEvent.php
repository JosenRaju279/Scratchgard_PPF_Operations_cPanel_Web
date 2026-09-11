<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TicketEvent extends Model {
    public $timestamps=false;
    protected $fillable=['ticket_id','actor_id','event_type','from_status','to_status','notes','metadata','created_at'];
    protected function casts(): array { return ['metadata'=>'array','created_at'=>'datetime']; }
}
