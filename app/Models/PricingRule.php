<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PricingRule extends Model {
    protected $fillable=['package_code','zone_id','vehicle_category','amount','currency','effective_from','effective_to','active'];
    public function zone(){ return $this->belongsTo(Zone::class); }
    protected function casts(): array { return ['amount'=>'decimal:2','effective_from'=>'date','effective_to'=>'date','active'=>'boolean']; }
}
