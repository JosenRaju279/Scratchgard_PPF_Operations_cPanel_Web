<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Review extends Model {
    protected $fillable=['work_order_id','reviewer_id','reviewer_role','decision','reason_id','comments','verification_method','identity_verification_id','created_at'];
    public $timestamps=false;
    protected function casts(): array { return ['created_at'=>'datetime']; }
    public function verification(){ return $this->belongsTo(IdentityVerification::class,'identity_verification_id'); }
}
