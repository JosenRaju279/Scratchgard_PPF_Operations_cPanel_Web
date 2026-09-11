<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Role extends Model {
    protected $fillable=['name','slug','is_internal'];
    protected function casts(): array { return ['is_internal'=>'boolean']; }
    public function permissions(){ return $this->belongsToMany(Permission::class,'role_permissions'); }
}
