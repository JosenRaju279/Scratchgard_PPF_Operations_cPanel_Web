<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Reason extends Model {
    protected $fillable=['category','code','label','allowed_roles','comment_required','evidence_required','target_status','payment_effect','active','sort_order'];
    protected function casts(): array { return ['allowed_roles'=>'array','comment_required'=>'boolean','evidence_required'=>'boolean','active'=>'boolean']; }
}
