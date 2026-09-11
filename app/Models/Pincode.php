<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Pincode extends Model {
    protected $fillable=['pincode','locality','district','state','country','latitude','longitude','office_name','division_name','region_name','circle_name','delivery_status','source_version'];
    public function zones(){return $this->belongsToMany(Zone::class,'zone_pincodes');}
}
