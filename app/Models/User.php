<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $fillable=['role_id','primary_zone_id','vendor_organization_id','showroom_id','name','username','email','pending_email','mobile','mobile_country_code','mobile_national_number','pending_mobile','password','pincode','address','city','district','state','country','status','email_verified_at','mobile_verified_at','profile_verified_at','employee_reference','designation','profile_photo_path','allow_multiple_zones','allow_zone_self_selection','approval_otp_required'];
    protected $hidden=['password','remember_token'];
    protected function casts(): array { return ['email_verified_at'=>'datetime','mobile_verified_at'=>'datetime','profile_verified_at'=>'datetime','password'=>'hashed','allow_multiple_zones'=>'boolean','allow_zone_self_selection'=>'boolean','approval_otp_required'=>'boolean']; }
    public function role(){return $this->belongsTo(Role::class);}    
    public function zones(){return $this->belongsToMany(Zone::class,'user_zones')->withTimestamps()->withPivot(['is_primary','assigned_by','source']);}
    public function primaryZone(){return $this->belongsTo(Zone::class,'primary_zone_id');}
    public function vendorOrganization(){return $this->belongsTo(VendorOrganization::class,'vendor_organization_id');}
    public function showroom(){return $this->belongsTo(Showroom::class);}
    public function kyc(){return $this->hasMany(KycRecord::class);}
    public function applicatorWorkOrders(){return $this->hasMany(WorkOrder::class,'applicator_id');}
    public function managedWorkOrders(){return $this->hasMany(WorkOrder::class,'zonal_manager_id');}
    public function hasPermission(string $permission): bool {
        if($this->role?->slug==='super_admin') return true;
        $override=DB::table('user_permission_overrides')->join('permissions','permissions.id','=','user_permission_overrides.permission_id')->where('user_id',$this->id)->where('permissions.slug',$permission)->value('allowed');
        if($override!==null) return (bool)$override;
        return DB::table('role_permissions')->join('permissions','permissions.id','=','role_permissions.permission_id')->where('role_id',$this->role_id)->where('permissions.slug',$permission)->exists();
    }
}
