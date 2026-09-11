<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ApiClient extends Model {
    protected $fillable=['name','client_id','secret_hash','abilities','allowed_ips','active','expires_at','last_used_at','created_by'];
    protected $hidden=['secret_hash'];
    protected function casts(): array { return ['abilities'=>'array','allowed_ips'=>'array','active'=>'boolean','expires_at'=>'datetime','last_used_at'=>'datetime']; }
    public function can(string $ability): bool { $a=$this->abilities ?: []; return in_array('*',$a,true)||in_array($ability,$a,true); }
}
