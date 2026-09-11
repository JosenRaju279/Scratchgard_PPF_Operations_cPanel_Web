<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Setting extends Model {
    protected $fillable=['key','value','type','group'];
    public static function getValue(string $key, mixed $default=null): mixed {
        $row=static::where('key',$key)->first();
        if(!$row) return $default;
        return match($row->type){
            'bool'=>(bool)$row->value,
            'int'=>(int)$row->value,
            'json'=>json_decode($row->value,true),
            'secret'=>($row->value ? decrypt($row->value) : null),
            default=>$row->value
        };
    }
}
