<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VendorOrganization extends Model {
    protected $fillable=['name','code','status'];
    public function showrooms(){ return $this->hasMany(Showroom::class,'vendor_organization_id'); }
}
