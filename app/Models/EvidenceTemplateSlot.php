<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EvidenceTemplateSlot extends Model {
    protected $fillable=['evidence_template_id','stage','slot_code','label','mandatory','camera_only','location_required','closeup_hint','sort_order'];
    protected function casts(): array { return ['mandatory'=>'boolean','camera_only'=>'boolean','location_required'=>'boolean']; }
    public function template(){ return $this->belongsTo(EvidenceTemplate::class,'evidence_template_id'); }
}
