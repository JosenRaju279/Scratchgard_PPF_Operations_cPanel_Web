<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Assignment extends Model {
    protected $fillable=['work_order_id','assignment_type','from_user_id','to_user_id','actor_id','reason_id','notes','started_at','ended_at','metadata'];
    protected function casts(): array { return ['started_at'=>'datetime','ended_at'=>'datetime','metadata'=>'array']; }
}
