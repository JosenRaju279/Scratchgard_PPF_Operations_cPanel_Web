<?php
namespace Database\Seeders;

use App\Models\EvidenceTemplate;
use App\Models\EvidenceTemplateSlot;
use App\Models\Permission;
use App\Models\Reason;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles=[
            ['name'=>'Super Admin','slug'=>'super_admin','is_internal'=>true],
            ['name'=>'Work Delegator Admin','slug'=>'work_delegator','is_internal'=>true],
            ['name'=>'Zonal Manager','slug'=>'zonal_manager','is_internal'=>true],
            ['name'=>'Applicator','slug'=>'applicator','is_internal'=>true],
            ['name'=>'External Verifier','slug'=>'external_verifier','is_internal'=>false],
            ['name'=>'Finance','slug'=>'finance','is_internal'=>true],
        ];
        foreach($roles as $r) Role::firstOrCreate(['slug'=>$r['slug']],$r);

        $permissions=[
            ['group'=>'work','slug'=>'work.create','name'=>'Create work'],['group'=>'work','slug'=>'work.delegate','name'=>'Delegate work'],
            ['group'=>'work','slug'=>'work.assign_applicator','name'=>'Assign/reassign applicator'],['group'=>'work','slug'=>'work.assign_verifier','name'=>'Assign external verifier'],
            ['group'=>'work','slug'=>'work.approve','name'=>'Approve completed work'],['group'=>'work','slug'=>'work.disapprove','name'=>'Return/reject/quality hold'],
            ['group'=>'work','slug'=>'work.override','name'=>'Override job actions'],['group'=>'work','slug'=>'work.change_rework_scope','name'=>'Change rework scope / create linked work'],
            ['group'=>'evidence','slug'=>'evidence.view','name'=>'View evidence'],
            ['group'=>'kyc','slug'=>'kyc.review','name'=>'Review KYC'],['group'=>'kyc','slug'=>'kyc.approve','name'=>'Approve KYC'],
            ['group'=>'pricing','slug'=>'pricing.view','name'=>'View pricing'],['group'=>'pricing','slug'=>'pricing.manage','name'=>'Manage pricing'],
            ['group'=>'payment','slug'=>'payment.view','name'=>'View payments'],['group'=>'payment','slug'=>'payment.manage','name'=>'Manage payments'],['group'=>'payment','slug'=>'payment.revert','name'=>'Revert/hold/release payment status'],
            ['group'=>'users','slug'=>'users.manage','name'=>'Manage users'],['group'=>'settings','slug'=>'settings.manage','name'=>'Manage system settings'],
            ['group'=>'audit','slug'=>'audit.view','name'=>'View audit trail'],['group'=>'masters','slug'=>'masters.manage','name'=>'Manage zones, location database, vendors and showrooms'],
            ['group'=>'reason','slug'=>'reasons.manage','name'=>'Manage reason selectors'],['group'=>'template','slug'=>'evidence_templates.manage','name'=>'Manage evidence templates'],
            ['group'=>'complaint','slug'=>'complaints.manage','name'=>'Manage complaints and rework'],['group'=>'ticket','slug'=>'tickets.view','name'=>'View assigned/participating tickets'],
            ['group'=>'ticket','slug'=>'tickets.manage','name'=>'Manage all tickets'],['group'=>'chat','slug'=>'chat.use','name'=>'Use work chat'],
            ['group'=>'api','slug'=>'api_clients.manage','name'=>'Manage third-party API clients'],
        ];
        foreach($permissions as $p) Permission::firstOrCreate(['slug'=>$p['slug']],$p);

        $rolePerms=[
            'work_delegator'=>['work.create','work.delegate','work.assign_verifier','evidence.view','audit.view','chat.use','tickets.view'],
            'zonal_manager'=>['work.assign_applicator','work.assign_verifier','work.disapprove','work.change_rework_scope','evidence.view','pricing.view','audit.view','chat.use','tickets.view'],
            'applicator'=>['evidence.view','chat.use','tickets.view'],
            'external_verifier'=>['evidence.view','work.disapprove','chat.use','tickets.view'],
            'finance'=>['payment.view','payment.manage','pricing.view','audit.view'],
        ];
        foreach($rolePerms as $slug=>$perms){$role=Role::where('slug',$slug)->first();$role->permissions()->syncWithoutDetaching(Permission::whereIn('slug',$perms)->pluck('id'));}

        $reasons=[
            ['category'=>'reassignment','code'=>'APPLICATOR_UNAVAILABLE','label'=>'Applicator unavailable','comment_required'=>true,'target_status'=>'APPLICATOR_ASSIGNED'],
            ['category'=>'reassignment','code'=>'QUALITY_CONCERN','label'=>'Quality concern / change applicator','comment_required'=>true,'target_status'=>'APPLICATOR_ASSIGNED'],
            ['category'=>'correction','code'=>'EDGE_FINISHING','label'=>'Edge finishing issue','comment_required'=>true,'evidence_required'=>true,'target_status'=>'CORRECTION_REQUIRED'],
            ['category'=>'correction','code'=>'BUBBLE','label'=>'Bubble / trapped air','comment_required'=>true,'evidence_required'=>true,'target_status'=>'CORRECTION_REQUIRED'],
            ['category'=>'correction','code'=>'ALIGNMENT','label'=>'Alignment issue','comment_required'=>true,'evidence_required'=>true,'target_status'=>'CORRECTION_REQUIRED'],
            ['category'=>'quality_hold','code'=>'MISTAKEN_APPROVAL','label'=>'Mistaken approval / quality hold','comment_required'=>true,'target_status'=>'QUALITY_HOLD','payment_effect'=>'block'],
            ['category'=>'rejection','code'=>'INVALID_WORK','label'=>'Invalid / unauthorised work','comment_required'=>true,'target_status'=>'REJECTED','payment_effect'=>'block'],
            ['category'=>'gps_exception','code'=>'GPS_UNAVAILABLE','label'=>'GPS unavailable / weak signal','comment_required'=>true],
            ['category'=>'kyc','code'=>'KYC_MISMATCH','label'=>'KYC information mismatch','comment_required'=>true],
            ['category'=>'payment','code'=>'PAYMENT_ADMIN_REVERT','label'=>'Administrative payment status correction','comment_required'=>true],
            ['category'=>'rework_scope','code'=>'OUT_OF_SCOPE_NEW_WORK','label'=>'Rework determined to be out of original scope / create linked new work','comment_required'=>true],
        ];
        foreach($reasons as $r) Reason::firstOrCreate(['code'=>$r['code']],$r);

        $tpl=EvidenceTemplate::firstOrCreate(['package_code'=>'FULL_PPF','version'=>1],['name'=>'Full PPF Default Checklist','active'=>true]);
        $slots=[
            ['BEFORE','DOOR_EDGES','Door edges and corners / inside-door film setting','Approx. 30 cm where practical'],['BEFORE','BUMPERS','Front/rear bumpers, corners and difficult contours','Close-up contours'],
            ['BEFORE','ROOF_BONNET','Roof and bonnet leading edges/corners','Close-up edges'],['BEFORE','LIGHT_AREAS','Areas around front and rear lights','Close-up'],
            ['BEFORE','OVERALL_DEFECTS','Overall views and pre-existing scratches/dents/dots/chips','Capture all visible defects'],['AFTER','DOOR_EDGES','Door edges and corners / inside-door film setting','Approx. 30 cm where practical'],
            ['AFTER','BUMPERS','Front/rear bumpers, corners and difficult contours','Close-up contours'],['AFTER','ROOF_BONNET','Roof and bonnet leading edges/corners','Close-up edges'],
            ['AFTER','LIGHT_AREAS','Areas around front and rear lights','Close-up'],['AFTER','OVERALL_VIEWS','Overall completed vehicle views','Clear full views'],
        ];
        foreach($slots as $i=>$slot) EvidenceTemplateSlot::firstOrCreate(['evidence_template_id'=>$tpl->id,'stage'=>$slot[0],'slot_code'=>$slot[1]],['label'=>$slot[2],'mandatory'=>true,'camera_only'=>true,'location_required'=>true,'closeup_hint'=>$slot[3],'sort_order'=>$i+1]);

        $settings=[
            ['key'=>'app_brand_name','value'=>'Scratchgard','type'=>'string','group'=>'branding'],['key'=>'footer_text','value'=>'Scratchgard PPF Operations','type'=>'string','group'=>'branding'],
            ['key'=>'theme_color','value'=>'#0B1727','type'=>'string','group'=>'branding'],['key'=>'accent_color','value'=>'#27B7E8','type'=>'string','group'=>'branding'],['key'=>'text_color','value'=>'#EDF7FF','type'=>'string','group'=>'branding'],
            ['key'=>'font_family','value'=>'Inter, ui-sans-serif, system-ui, sans-serif','type'=>'string','group'=>'branding'],['key'=>'base_font_size','value'=>'16','type'=>'int','group'=>'branding'],['key'=>'heading_scale','value'=>'1.22','type'=>'string','group'=>'branding'],
            ['key'=>'evidence_retention_days','value'=>'365','type'=>'int','group'=>'evidence'],['key'=>'default_geofence_radius_m','value'=>'250','type'=>'int','group'=>'location'],['key'=>'payment_tracking_mode','value'=>'approval_status','type'=>'string','group'=>'payment'],
            ['key'=>'allow_zonal_manager_approval','value'=>'0','type'=>'bool','group'=>'permissions'],['key'=>'allow_zonal_manager_kyc_approval','value'=>'0','type'=>'bool','group'=>'permissions'],
            ['key'=>'registration_identity_mode','value'=>'email_or_mobile','type'=>'string','group'=>'registration'],['key'=>'registration_zone_selection_mode','value'=>'hidden_auto','type'=>'string','group'=>'registration'],['key'=>'default_mobile_country_code','value'=>'+91','type'=>'string','group'=>'registration'],
            ['key'=>'registration_require_existing_zone','value'=>'1','type'=>'bool','group'=>'registration'],['key'=>'allow_multiple_zones_default','value'=>'0','type'=>'bool','group'=>'zones'],
            ['key'=>'user_self_zone_change','value'=>'1','type'=>'bool','group'=>'zones'],['key'=>'otp_expiry_minutes','value'=>'10','type'=>'int','group'=>'registration'],
            ['key'=>'mobile_otp_driver','value'=>'log','type'=>'string','group'=>'registration'],['key'=>'mobile_otp_http_url','value'=>'','type'=>'string','group'=>'registration'],
            ['key'=>'email_notifications_enabled','value'=>'1','type'=>'bool','group'=>'email'],['key'=>'email_notify_super_admins','value'=>'1','type'=>'bool','group'=>'email'],['key'=>'email_notify_work_delegators','value'=>'1','type'=>'bool','group'=>'email'],
        ];
        foreach($settings as $s) Setting::firstOrCreate(['key'=>$s['key']],$s);
    }
}
