<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EvidenceTemplate extends Model {
    protected $fillable=['name','package_code','version','active'];
    protected function casts(): array { return ['active'=>'boolean']; }
    public function slots(){ return $this->hasMany(EvidenceTemplateSlot::class); }
}
