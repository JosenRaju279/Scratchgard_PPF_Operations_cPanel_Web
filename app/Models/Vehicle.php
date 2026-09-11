<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Vehicle extends Model {
    protected $fillable=['vin','registration_number','make','model','variant','color','model_year','external_reference'];
    public function workOrders(){ return $this->hasMany(WorkOrder::class); }
}
