<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Checkin extends Model {
    protected $fillable=['work_order_id','user_id','type','latitude','longitude','accuracy','distance_from_showroom_m','within_geofence','exception_reason_id','exception_notes','checked_at'];
    protected function casts(): array { return ['within_geofence'=>'boolean','checked_at'=>'datetime']; }
}
