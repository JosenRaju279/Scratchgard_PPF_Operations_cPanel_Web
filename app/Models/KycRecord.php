<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class KycRecord extends Model {
    protected $fillable=['user_id','document_type','document_number','document_name','document_expiry','front_path','back_path','address_proof_path','selfie_path','status','reviewed_by','reviewed_at','reason_id','notes'];
    public function user(){ return $this->belongsTo(User::class); }
    protected function casts(): array { return ['document_expiry'=>'date','reviewed_at'=>'datetime']; }
}
