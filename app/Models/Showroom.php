<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Showroom extends Model {
    protected $fillable=['vendor_organization_id','zone_id','name','code','address','pincode','city','district','state','country','latitude','longitude','geofence_radius_m','status'];
    public function vendor(){ return $this->belongsTo(VendorOrganization::class,'vendor_organization_id'); }
    public function zone(){ return $this->belongsTo(Zone::class); }
}
