<?php
namespace App\Services;

use App\Models\IdentityVerification;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class IdentityVerificationService
{
    public function issue(User $user, string $channel, string $destination, string $purpose='registration'): IdentityVerification
    {
        $code=(string)random_int(100000,999999);
        $row=IdentityVerification::create([
            'user_id'=>$user->id,'channel'=>$channel,'destination'=>$destination,'purpose'=>$purpose,
            'code_hash'=>hash('sha256',$code),'expires_at'=>now()->addMinutes((int)Setting::getValue('otp_expiry_minutes',10)),
        ]);
        if($channel==='email'){
            $review=str_starts_with($purpose,'work_review:');
            $subject=$review?'Scratchgard work review OTP':'Scratchgard verification code';
            $message=$review?"Your Scratchgard work review OTP is {$code}. Use it only to confirm the assigned work review. It expires shortly.":"Your Scratchgard verification code is {$code}. It expires shortly.";
            Mail::raw($message,function($m) use($destination,$subject){$m->to($destination)->subject($subject);});
        } elseif($channel==='mobile') {
            $this->sendMobile($destination,$code);
        } else throw new RuntimeException('Unsupported verification channel.');
        return $row;
    }

    public function verify(User $user, string $channel, string $code, string $purpose='registration'): bool
    {
        return (bool)$this->verifyRow($user,$channel,$code,$purpose);
    }

    public function verifyRow(User $user, string $channel, string $code, string $purpose='registration'): ?IdentityVerification
    {
        $row=IdentityVerification::where('user_id',$user->id)->where('channel',$channel)->where('purpose',$purpose)
            ->whereNull('verified_at')->where('expires_at','>',now())->latest()->first();
        if(!$row) return null;
        $row->increment('attempts');
        if($row->attempts>6) return null;
        if(!hash_equals($row->code_hash,hash('sha256',trim($code)))) return null;
        $row->update(['verified_at'=>now()]);
        if($channel==='email') $user->update(['email_verified_at'=>now()]);
        if($channel==='mobile') $user->update(['mobile_verified_at'=>now()]);
        return $row->fresh();
    }

    private function sendMobile(string $mobile, string $code): void
    {
        $driver=(string)Setting::getValue('mobile_otp_driver','log');
        if($driver==='log'){
            Log::warning("Scratchgard mobile OTP for {$mobile}: {$code}. Configure a production SMS provider before live use.");
            return;
        }
        if($driver==='generic_http'){
            $url=(string)Setting::getValue('mobile_otp_http_url','');
            if(!$url) throw new RuntimeException('Mobile OTP HTTP URL is not configured.');
            $token=(string)Setting::getValue('mobile_otp_http_token','');
            $payload=['mobile'=>$mobile,'code'=>$code,'message'=>"Your Scratchgard verification code is {$code}"];
            $req=Http::timeout(15)->acceptJson();
            if($token) $req=$req->withToken($token);
            $resp=$req->post($url,$payload);
            if(!$resp->successful()) throw new RuntimeException('SMS provider returned HTTP '.$resp->status());
            return;
        }
        throw new RuntimeException('Unknown mobile OTP driver. Install/configure an SMS plugin/provider.');
    }
}
