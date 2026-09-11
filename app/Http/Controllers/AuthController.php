<?php
namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Services\IdentityNormalizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        $portal = $this->portalFromRequest($request);
        return view('auth.login', compact('portal'));
    }

    public function login(Request $request, IdentityNormalizerService $identity)
    {
        $portal = $this->portalFromRequest($request);
        $v=$request->validate(['identifier'=>'required|string|max:190','password'=>'required|string']);
        $raw=trim($v['identifier']);
        $user=null;

        if(str_contains($raw,'@')){
            $email=$identity->normalizeEmail($raw);
            $user=$email?User::where('email',$email)->first():null;
        } elseif(preg_match('/^[+\d\s().-]+$/',$raw) && strlen(preg_replace('/\D+/','',$raw))>=10){
            try{
                $mobile=$identity->normalizeMobile($raw,null,(string)Setting::getValue('default_mobile_country_code','+91'))['canonical'];
                $user=User::where('mobile',$mobile)->first();
            }catch(\Throwable $ignored){}
        } else {
            $username=$identity->normalizeUsername($raw);
            $user=$username?User::where('username',$username)->first():null;
        }

        if(!$user || !Auth::attempt(['id'=>$user->id,'password'=>$v['password']],$request->boolean('remember'))){
            return back()->withErrors(['identifier'=>'Invalid username, email, mobile or password.'])->onlyInput('identifier');
        }

        if($portal && !in_array($user->role?->slug, $portal['roles'] ?? [], true)){
            Auth::logout();
            return back()->withErrors(['identifier'=>'This account does not have access to the '.$portal['title'].'. Use the correct portal for your role.'])->onlyInput('identifier');
        }

        if($user->status!=='active'){
            Auth::logout();
            $message=match($user->status){'verification_pending'=>'Complete required email/mobile verification first.','pending'=>'Your account/KYC is awaiting Scratchgard approval.','suspended'=>'Your account is suspended.',default=>'This account is not active.'};
            return back()->withErrors(['identifier'=>$message]);
        }
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        $role = $request->user()?->role?->slug;
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $route = $this->loginRouteForRole($role);
        return redirect()->route($route);
    }

    private function portalFromRequest(Request $request): ?array
    {
        $key = $request->route('portal');
        if(!$key) return null;
        return config('portals.'.$key);
    }

    private function loginRouteForRole(?string $role): string
    {
        foreach(config('portals',[]) as $portal){
            if(in_array($role,$portal['roles'] ?? [],true)) return $portal['route'];
        }
        return 'login';
    }
}
