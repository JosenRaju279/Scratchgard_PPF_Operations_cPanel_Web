<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Plugin extends Model {
    protected $fillable=['slug','name','description','author','homepage','version','previous_version','path','enabled','manifest','settings_schema','registered_permissions','checksum','last_error','installed_by','installed_at','activated_at','disabled_at'];
    protected function casts(): array { return ['enabled'=>'boolean','manifest'=>'array','settings_schema'=>'array','registered_permissions'=>'array','installed_at'=>'datetime','activated_at'=>'datetime','disabled_at'=>'datetime']; }
    public function settings(){return $this->hasMany(PluginSetting::class);}
    public function activity(){return $this->hasMany(PluginActivityLog::class)->latest('created_at');}
}
