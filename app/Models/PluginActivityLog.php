<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PluginActivityLog extends Model
{
    public $timestamps=false;
    protected $fillable=['plugin_id','actor_id','action','message','metadata','created_at'];
    protected function casts(): array { return ['metadata'=>'array','created_at'=>'datetime']; }
    public function plugin(){return $this->belongsTo(Plugin::class);}
    public function actor(){return $this->belongsTo(User::class,'actor_id');}
}
