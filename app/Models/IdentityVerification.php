<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class IdentityVerification extends Model {
    protected $fillable=['user_id','channel','destination','purpose','code_hash','expires_at','verified_at','attempts','metadata'];
    protected function casts(): array { return ['expires_at'=>'datetime','verified_at'=>'datetime','metadata'=>'array']; }
    public function user(){ return $this->belongsTo(User::class); }
}
