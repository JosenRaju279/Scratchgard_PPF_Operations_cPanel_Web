<?php
namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SettingsController extends Controller
{
    public function index(){return view('settings.index',['settings'=>Setting::all()->keyBy('key'),'notificationLogs'=>NotificationLog::latest()->limit(30)->get()]);}
    public function update(Request $request)
    {
        $v=$request->validate([
            'app_brand_name'=>'required|string|max:120','footer_text'=>'nullable|string|max:300','theme_color'=>'required|regex:/^#[0-9A-Fa-f]{6}$/','accent_color'=>'required|regex:/^#[0-9A-Fa-f]{6}$/','text_color'=>'required|regex:/^#[0-9A-Fa-f]{6}$/','font_family'=>'required|string|max:250','base_font_size'=>'required|integer|min:12|max:22','heading_scale'=>'required|numeric|min:1|max:2',
            'evidence_retention_days'=>'required|integer|min:1|max:3650','default_geofence_radius_m'=>'required|integer|min:10|max:10000','payment_tracking_mode'=>'required|in:approval_status,manual_finance',
            'registration_identity_mode'=>'required|in:email_only,mobile_only,email_or_mobile,both','registration_zone_selection_mode'=>'required|in:hidden_auto,show_single,show_multi','default_mobile_country_code'=>'required|regex:/^\+?[0-9]{1,4}$/','otp_expiry_minutes'=>'required|integer|min:2|max:60','mobile_otp_driver'=>'required|in:log,generic_http','mobile_otp_http_url'=>'nullable|url|max:1000','mobile_otp_http_token'=>'nullable|string|max:2000',
            'smtp_host'=>'nullable|string|max:190','smtp_port'=>'nullable|integer|min:1|max:65535','smtp_username'=>'nullable|string|max:190','smtp_password'=>'nullable|string|max:500','smtp_from_address'=>'nullable|email|max:190','smtp_from_name'=>'nullable|string|max:190',
            'logo'=>'nullable|image|max:4096','favicon'=>'nullable|image|max:2048',
        ]);
        $defs=[
            'app_brand_name'=>['string','branding'],'footer_text'=>['string','branding'],'theme_color'=>['string','branding'],'accent_color'=>['string','branding'],'text_color'=>['string','branding'],'font_family'=>['string','branding'],'base_font_size'=>['int','branding'],'heading_scale'=>['string','branding'],
            'evidence_retention_days'=>['int','evidence'],'default_geofence_radius_m'=>['int','location'],'payment_tracking_mode'=>['string','payment'],'registration_identity_mode'=>['string','registration'],'registration_zone_selection_mode'=>['string','registration'],'default_mobile_country_code'=>['string','registration'],'otp_expiry_minutes'=>['int','registration'],'mobile_otp_driver'=>['string','registration'],'mobile_otp_http_url'=>['string','registration'],
        ];
        foreach($defs as $key=>$meta)Setting::updateOrCreate(['key'=>$key],['value'=>(string)($v[$key] ?? ''),'type'=>$meta[0],'group'=>$meta[1]]);
        foreach(['allow_zonal_manager_approval','allow_zonal_manager_kyc_approval','registration_require_existing_zone','allow_multiple_zones_default','user_self_zone_change','email_notifications_enabled','email_notify_super_admins','email_notify_work_delegators'] as $key)Setting::updateOrCreate(['key'=>$key],['value'=>$request->boolean($key)?'1':'0','type'=>'bool','group'=>str_contains($key,'zone')?'zones':'permissions']);
        if($request->filled('mobile_otp_http_token'))Setting::updateOrCreate(['key'=>'mobile_otp_http_token'],['value'=>encrypt((string)$request->input('mobile_otp_http_token')),'type'=>'secret','group'=>'registration']);
        if($request->hasFile('logo'))Setting::updateOrCreate(['key'=>'brand_logo_path'],['value'=>$request->file('logo')->store('branding','public'),'type'=>'string','group'=>'branding']);
        if($request->hasFile('favicon'))Setting::updateOrCreate(['key'=>'brand_favicon_path'],['value'=>$request->file('favicon')->store('branding','public'),'type'=>'string','group'=>'branding']);
        foreach(['smtp_host'=>['string','email'],'smtp_port'=>['int','email'],'smtp_username'=>['string','email'],'smtp_from_address'=>['string','email'],'smtp_from_name'=>['string','email']] as $key=>$meta)if($request->filled($key))Setting::updateOrCreate(['key'=>$key],['value'=>(string)$request->input($key),'type'=>$meta[0],'group'=>$meta[1]]);
        if($request->filled('smtp_password'))Setting::updateOrCreate(['key'=>'smtp_password'],['value'=>encrypt((string)$request->input('smtp_password')),'type'=>'secret','group'=>'email']);
        $zonal=Role::where('slug','zonal_manager')->first();if($zonal){$approve=Permission::where('slug','work.approve')->first();if($approve)$request->boolean('allow_zonal_manager_approval')?$zonal->permissions()->syncWithoutDetaching([$approve->id]):$zonal->permissions()->detach($approve->id);$kycIds=Permission::whereIn('slug',['kyc.review','kyc.approve'])->pluck('id')->all();$request->boolean('allow_zonal_manager_kyc_approval')?$zonal->permissions()->syncWithoutDetaching($kycIds):$zonal->permissions()->detach($kycIds);}
        return back()->with('status','Branding, registration, zone, verification and permission policies updated.');
    }
    public function testMail(Request $request){$v=$request->validate(['test_email'=>'required|email']);try{Mail::raw('Scratchgard SMTP test successful at '.now()->toDateTimeString(),fn($m)=>$m->to($v['test_email'])->subject('Scratchgard SMTP Test'));return back()->with('status','Test email sent/queued successfully.');}catch(\Throwable $e){return back()->withErrors(['smtp'=>'SMTP test failed: '.$e->getMessage()]);}}
}
