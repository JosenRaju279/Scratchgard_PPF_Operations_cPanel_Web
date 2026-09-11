<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TicketMessage extends Model {
    protected $fillable=['ticket_id','sender_id','message','attachments','internal_only'];
    protected function casts(): array { return ['attachments'=>'array','internal_only'=>'boolean']; }
    public function sender(){ return $this->belongsTo(User::class,'sender_id'); }
    public function ticket(){ return $this->belongsTo(Ticket::class); }
}
