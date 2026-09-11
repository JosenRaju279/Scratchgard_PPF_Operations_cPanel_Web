<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PluginSetting extends Model
{
    protected $fillable=['plugin_id','key','value','encrypted'];
    protected function casts(): array { return ['encrypted'=>'boolean']; }
    public function plugin(){return $this->belongsTo(Plugin::class);}

    public function decodedValue(mixed $default=null): mixed
    {
        if($this->value===null) return $default;
        $raw=$this->encrypted ? Crypt::decryptString($this->value) : $this->value;
        $decoded=json_decode($raw,true);
        return json_last_error()===JSON_ERROR_NONE ? $decoded : $raw;
    }

    public static function put(Plugin $plugin,string $key,mixed $value,bool $encrypted=false): self
    {
        $raw=json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        return static::updateOrCreate(['plugin_id'=>$plugin->id,'key'=>$key],['value'=>$encrypted?Crypt::encryptString($raw):$raw,'encrypted'=>$encrypted]);
    }
}
