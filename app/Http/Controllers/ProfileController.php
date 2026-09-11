<?php
namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Services\IdentityNormalizerService;
use App\Services\IdentityVerificationService;
use App\Services\UserZoneService;
use App\Services\ZoneResolverService;
use Illuminate\Http\Request;
use App\Plugins\PluginRuntime;

class ProfileController extends Controller
{
    public function edit(){return view('profile.edit',['user'=>auth()->user()->load(['primaryZone','zones','showroom.vendor']),'zoneSelectionAllowed'=>$this->zoneSelectionAllowed(auth()->user()),'defaultCountryCode'=>(string)Setting::getValue('default_mobile_country_code','+91')]);}
    public function resolveZone(Request $request, ZoneResolverService $resolver){$v=$request->validate(['pincode'=>'required|digits:6']);return response()->json($resolver->resolvePincode($v['pincode']));}
    public function usernameAvailability(Request $request, IdentityNormalizerService $identity)
    {
        $u=$request->user();$username=$identity->normalizeUsername((string)$request->query('username',''));
        if(!$username || !$identity->usernameValid($username))return response()->json(['available'=>false,'normalized'=>$username,'message'=>'Use 4–30 lowercase letters, numbers, dot or underscore.']);
        $available=!User::where('username',$username)->where('id','!=',$u->id)->exists();
        return response()->json(['available'=>$available,'normalized'=>$username,'message'=>$available?'Username available.':'Username already taken.']);
    }

    public function update(Request $request, ZoneResolverService $resolver, UserZoneService $zoneService, IdentityVerificationService $verify, IdentityNormalizerService $identity)
    {
        $user=$request->user();$email=$identity->normalizeEmail($request->input('email'));$username=$identity->normalizeUsername($request->input('username'));
        $rawMobile=trim((string)$request->input('mobile'));
        $mobileParts=$rawMobile!==''?$identity->normalizeMobile($rawMobile,$request->input('mobile_country_code'),(string)Setting::getValue('default_mobile_country_code','+91')):['country_code'=>null,'national_number'=>null,'canonical'=>null];
        $request->merge(['username'=>$username,'email'=>$email,'mobile'=>$mobileParts['canonical'],'mobile_country_code'=>$mobileParts['country_code'],'mobile_national_number'=>$mobileParts['national_number']]);
        $v=$request->validate([
            'name'=>'required|string|max:120','username'=>'required|string|min:4|max:30|regex:/^(?=.*[a-z])[a-z0-9][a-z0-9._]*[a-z0-9]$/|unique:users,username,'.$user->id,
            'email'=>'nullable|email:rfc|max:190|unique:users,email,'.$user->id,'mobile'=>'nullable|string|max:30|unique:users,mobile,'.$user->id,
            'mobile_country_code'=>'nullable|string|max:8','mobile_national_number'=>'nullable|digits:10','pincode'=>'required|digits:6','address'=>'required|string|max:1000','profile_photo'=>'nullable|image|max:8192','zone_id'=>'nullable|exists:zones,id'
        ]);
        $resolved=$resolver->resolvePincode($v['pincode']);if(empty($resolved['matches']))return back()->withInput()->withErrors(['pincode'=>'No active Scratchgard zone exists for this PIN code.']);
        $zoneId=(int)$resolved['matches'][0]['zone_id'];
        if($this->zoneSelectionAllowed($user)&&!empty($v['zone_id'])){$allowed=collect($resolved['matches'])->pluck('zone_id')->map(fn($x)=>(int)$x)->all();if(!in_array((int)$v['zone_id'],$allowed,true))return back()->withErrors(['zone_id'=>'Selected zone does not match this PIN code.']);$zoneId=(int)$v['zone_id'];}
        $data=['name'=>$v['name'],'username'=>$v['username'],'pincode'=>$v['pincode'],'address'=>$v['address'],'city'=>$resolved['postal']['city']??$user->city,'district'=>$resolved['postal']['district']??$user->district,'state'=>$resolved['postal']['state']??$user->state];
        if($request->hasFile('profile_photo'))$data['profile_photo_path']=$request->file('profile_photo')->store('profiles','public');

        $emailChanged=($v['email']??null)!==$user->email;$mobileChanged=($v['mobile']??null)!==$user->mobile;
        if($emailChanged){$data['pending_email']=$v['email']??null;if($v['email'])$verify->issue($user,'email',$v['email'],'profile_update');}
        if($mobileChanged){$data['pending_mobile']=$v['mobile']??null;if($v['mobile'])$verify->issue($user,'mobile',$v['mobile'],'profile_update');}
        $user->update($data);
        if((int)$user->primary_zone_id!==$zoneId&&Setting::getValue('user_self_zone_change',true))$zoneService->assign($user,[$zoneId],$user,'self_profile',$zoneId,false);
        app(PluginRuntime::class)->doAction('user.profile.updated',$user->fresh(),(array)$request->input('plugin_fields',[]),$request);
        return back()->with('status','Profile updated.'.(($emailChanged||$mobileChanged)?' Verify new contact value(s) before they replace the existing verified contact.':'').' Username: '.$user->fresh()->username);
    }

    public function verifyContact(Request $request, IdentityVerificationService $service, IdentityNormalizerService $identity)
    {
        $user=$request->user();$v=$request->validate(['email_code'=>'nullable|digits:6','mobile_code'=>'nullable|digits:6']);
        if($user->pending_email&&!empty($v['email_code'])&&$service->verify($user,'email',$v['email_code'],'profile_update'))$user->update(['email'=>$identity->normalizeEmail($user->pending_email),'pending_email'=>null,'email_verified_at'=>now()]);
        if($user->pending_mobile&&!empty($v['mobile_code'])&&$service->verify($user,'mobile',$v['mobile_code'],'profile_update')){
            $parts=$identity->normalizeMobile($user->pending_mobile,null,(string)Setting::getValue('default_mobile_country_code','+91'));
            $user->update(['mobile'=>$parts['canonical'],'mobile_country_code'=>$parts['country_code'],'mobile_national_number'=>$parts['national_number'],'pending_mobile'=>null,'mobile_verified_at'=>now()]);
        }
        return back()->with('status','Verification attempt processed.');
    }
    private function zoneSelectionAllowed(User $u): bool{return $u->allow_zone_self_selection??in_array(Setting::getValue('registration_zone_selection_mode','hidden_auto'),['show_single','show_multi'],true);}
}
