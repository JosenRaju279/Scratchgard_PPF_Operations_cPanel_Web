<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ZoneCoverageRule extends Model {
    protected $fillable=['zone_id','scope_type','state','district','city','pincode','priority','active','created_by'];
    protected function casts(): array { return ['active'=>'boolean']; }
    public function zone(){ return $this->belongsTo(Zone::class); }
}
