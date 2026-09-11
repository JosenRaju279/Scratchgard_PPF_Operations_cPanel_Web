<?php
namespace App\Http\Controllers;

use App\Models\KycRecord;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\IdentityNormalizerService;
use App\Services\IdentityVerificationService;
use App\Services\UserZoneService;
use App\Services\ZoneResolverService;
use App\Services\SystemNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Plugins\PluginRuntime;

class ApplicatorRegistrationController extends Controller
{
    public function create()
    {
        return view('auth.applicator-register',[
            'identityMode'=>Setting::getValue('registration_identity_mode','email_or_mobile'),
            'zoneMode'=>Setting::getValue('registration_zone_selection_mode','hidden_auto'),
            'allowMulti'=>(bool)Setting::getValue('allow_multiple_zones_default',false),
            'defaultCountryCode'=>(string)Setting::getValue('default_mobile_country_code','+91'),
        ]);
    }

    public function usernameAvailability(Request $request, IdentityNormalizerService $identity)
    {
        $raw=(string)$request->query('username','');
        $username=$identity->normalizeUsername($raw);
        if(!$username || !$identity->usernameValid($username)) return response()->json(['available'=>false,'normalized'=>$username,'message'=>'Use 4–30 characters: lowercase letters, numbers, dot or underscore.']);
        $available=!User::where('username',$username)->exists();
        return response()->json(['available'=>$available,'normalized'=>$username,'message'=>$available?'Username available.':'Username already taken.']);
    }

    public function resolveZone(Request $request, ZoneResolverService $resolver)
    {
        $v=$request->validate(['pincode'=>'required|digits:6']);
        return response()->json($resolver->resolvePincode($v['pincode']));
    }

    public function store(Request $request, ZoneResolverService $resolver, UserZoneService $zones, IdentityVerificationService $verification, IdentityNormalizerService $identity, SystemNotificationService $notifications)
    {
        $mode=(string)Setting::getValue('registration_identity_mode','email_or_mobile');
        $zoneMode=(string)Setting::getValue('registration_zone_selection_mode','hidden_auto');
        $email=$identity->normalizeEmail($request->input('email'));
        $rawMobile=trim((string)$request->input('mobile'));
        $mobileParts=$rawMobile!=='' ? $identity->normalizeMobile($rawMobile,$request->input('mobile_country_code'),(string)Setting::getValue('default_mobile_country_code','+91')) : ['country_code'=>null,'national_number'=>null,'canonical'=>null];
        $username=$identity->normalizeUsername($request->input('username'));
        $request->merge(['email'=>$email,'mobile'=>$mobileParts['canonical'],'mobile_country_code'=>$mobileParts['country_code'],'mobile_national_number'=>$mobileParts['national_number'],'username'=>$username]);

        $rules=[
            'name'=>'required|string|max:120','username'=>'nullable|string|min:4|max:30|regex:/^(?=.*[a-z])[a-z0-9][a-z0-9._]*[a-z0-9]$/|unique:users,username',
            'email'=>'nullable|email:rfc|max:190|unique:users,email','mobile_country_code'=>'nullable|string|max:8','mobile_national_number'=>'nullable|digits:10','mobile'=>'nullable|string|max:30|unique:users,mobile',
            'password'=>'required|string|min:10|confirmed','pincode'=>'required|digits:6','address'=>'required|string|max:1000',
            'document_type'=>'required|string|max:80','document_number'=>'required|string|max:120','document_name'=>'nullable|string|max:120',
            'document_front'=>'nullable|image|max:8192','document_back'=>'nullable|image|max:8192','address_proof'=>'nullable|image|max:8192','selfie'=>'nullable|image|max:8192','profile_photo'=>'nullable|image|max:8192',
            'zone_id'=>'nullable|integer|exists:zones,id','zone_ids'=>'nullable|array','zone_ids.*'=>'exists:zones,id',
        ];
        if($mode==='email_only')$rules['email']='required|email:rfc|max:190|unique:users,email';
        if($mode==='mobile_only'){$rules['mobile']='required|string|max:30|unique:users,mobile';$rules['mobile_national_number']='required|digits:10';}
        if($mode==='both'){$rules['email']='required|email:rfc|max:190|unique:users,email';$rules['mobile']='required|string|max:30|unique:users,mobile';$rules['mobile_national_number']='required|digits:10';}
        $v=$request->validate($rules);
        if($mode==='email_or_mobile' && empty($v['email']) && empty($v['mobile'])) return back()->withInput()->withErrors(['email'=>'Enter at least one unique email address or mobile number.']);

        $resolved=$resolver->resolvePincode($v['pincode']);
        $matches=collect($resolved['matches']);
        if($matches->isEmpty() && Setting::getValue('registration_require_existing_zone',true)) return back()->withInput()->withErrors(['pincode'=>'No Scratchgard service zone exists for this PIN code. Registration cannot continue.']);

        $selected=[];
        if($zoneMode==='show_multi' && Setting::getValue('allow_multiple_zones_default',false)){
            $requested=array_map('intval',$v['zone_ids'] ?? []);$allowed=$matches->pluck('zone_id')->map(fn($x)=>(int)$x)->all();$selected=array_values(array_intersect($requested,$allowed));
            if(!$selected && $matches->isNotEmpty()) return back()->withInput()->withErrors(['zone_ids'=>'Select at least one zone resolved from your PIN code.']);
        } elseif($zoneMode==='show_single' || ($zoneMode==='show_multi' && !Setting::getValue('allow_multiple_zones_default',false))) {
            $zoneId=(int)($v['zone_id'] ?? 0);$allowed=$matches->pluck('zone_id')->map(fn($x)=>(int)$x)->all();
            if(!$zoneId || !in_array($zoneId,$allowed,true)) return back()->withInput()->withErrors(['zone_id'=>'Choose a valid zone shown for your PIN code.']);
            $selected=[$zoneId];
        } elseif($matches->isNotEmpty()) $selected=[(int)$matches->first()['zone_id']];

        $role=Role::where('slug','applicator')->firstOrFail();
        $finalUsername=$v['username'] ?? null;
        if(!$finalUsername)$finalUsername=$identity->generateUsername($v['name']);
        $userData=[
            'role_id'=>$role->id,'name'=>$v['name'],'username'=>$finalUsername,'email'=>$v['email'] ?? null,
            'mobile'=>$v['mobile'] ?? null,'mobile_country_code'=>$v['mobile_country_code'] ?? null,'mobile_national_number'=>$v['mobile_national_number'] ?? null,'password'=>Hash::make($v['password']),
            'pincode'=>$v['pincode'],'address'=>$v['address'],'city'=>$resolved['postal']['city'] ?? null,'district'=>$resolved['postal']['district'] ?? null,'state'=>$resolved['postal']['state'] ?? null,
            'country'=>'India','status'=>'verification_pending','allow_multiple_zones'=>count($selected)>1,
        ];
        $userData=app(PluginRuntime::class)->applyFilters('user.registration.data',$userData,$request,$resolved,$selected);
        $user=User::create($userData);
        app(PluginRuntime::class)->doAction('user.registered',$user,$request,$resolved,$selected);
        if($selected) $zones->assign($user,$selected,null,'self_registration',$selected[0],count($selected)>1);

        $disk=env('EVIDENCE_DISK','local');$base="kyc/{$user->id}";$store=fn($key)=>$request->file($key)?Storage::disk($disk)->putFile($base,$request->file($key)):null;
        if($request->file('profile_photo'))$user->update(['profile_photo_path'=>$store('profile_photo')]);
        KycRecord::create(['user_id'=>$user->id,'document_type'=>$v['document_type'],'document_number'=>$v['document_number'],'document_name'=>$v['document_name'] ?? null,'front_path'=>$store('document_front'),'back_path'=>$store('document_back'),'address_proof_path'=>$store('address_proof'),'selfie_path'=>$store('selfie'),'status'=>'submitted']);

        try{if($user->email)$verification->issue($user,'email',$user->email,'registration');if($user->mobile)$verification->issue($user,'mobile',$user->mobile,'registration');}
        catch(\Throwable $e){report($e);session()->flash('verification_warning','Account created, but an OTP could not be delivered: '.$e->getMessage().'. An administrator can help complete verification.');}
        try{$notifications->userRegistration($user,'APPLICATOR_REGISTERED','A new Applicator registration was created. Username: @'.$user->username.'. KYC is submitted and identity verification is pending.');}catch(\Throwable $e){report($e);}
        $request->session()->put('registration_user_id',$user->id);
        return redirect()->route('applicator.register.verify')->with('status','Registration created. Username: '.$user->username.'. Verify your contact details, then Scratchgard will review KYC.');
    }

    public function verifyForm(Request $request){$user=User::find($request->session()->get('registration_user_id'));abort_unless($user,404);return view('auth.applicator-verify',compact('user'));}

    public function verify(Request $request, IdentityVerificationService $verification, SystemNotificationService $notifications)
    {
        $user=User::find($request->session()->get('registration_user_id'));abort_unless($user,404);$v=$request->validate(['email_code'=>'nullable|digits:6','mobile_code'=>'nullable|digits:6']);$errors=[];
        if($user->email && !$user->email_verified_at){if(empty($v['email_code']) || !$verification->verify($user,'email',$v['email_code'],'registration'))$errors['email_code']='Email verification code is invalid or expired.';}
        if($user->mobile && !$user->mobile_verified_at){if(empty($v['mobile_code']) || !$verification->verify($user,'mobile',$v['mobile_code'],'registration'))$errors['mobile_code']='Mobile verification code is invalid or expired.';}
        if($errors)return back()->withErrors($errors);$user->refresh();
        if((!$user->email || $user->email_verified_at) && (!$user->mobile || $user->mobile_verified_at)){$user->update(['status'=>'pending','profile_verified_at'=>now()]);app(PluginRuntime::class)->doAction('user.identity.verified',$user);try{$notifications->userRegistration($user,'APPLICATOR_IDENTITY_VERIFIED','Applicator identity verification completed. KYC is now awaiting Scratchgard review.');}catch(\Throwable $e){report($e);}$request->session()->forget('registration_user_id');return redirect()->route('login')->with('status','Identity verified. Your KYC is now pending Scratchgard approval. You can later sign in with username, email or mobile.');}
        return back()->with('status','Verification partially completed.');
    }

    public function resend(Request $request, IdentityVerificationService $verification){$user=User::find($request->session()->get('registration_user_id'));abort_unless($user,404);if($user->email&&!$user->email_verified_at)$verification->issue($user,'email',$user->email,'registration');if($user->mobile&&!$user->mobile_verified_at)$verification->issue($user,'mobile',$user->mobile,'registration');return back()->with('status','Fresh verification code(s) sent.');}
}
