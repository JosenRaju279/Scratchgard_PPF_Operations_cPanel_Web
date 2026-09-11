<?php
namespace App\Http\Controllers;

use App\Models\KycRecord;
use App\Models\Reason;
use App\Services\SystemNotificationService;
use Illuminate\Http\Request;
use App\Plugins\PluginRuntime;

class KycController extends Controller
{
    public function index(){return view('kyc.index',['records'=>KycRecord::with('user')->latest()->paginate(30),'reasons'=>Reason::where('category','kyc')->where('active',1)->orderBy('label')->get()]);}
    public function review(Request $request,KycRecord $kyc, SystemNotificationService $notifications)
    {
        $v=$request->validate(['decision'=>'required|in:approved,rejected,resubmission_required','reason_id'=>'nullable|exists:reasons,id','notes'=>'nullable|string|max:2000']);
        if(auth()->user()->role?->slug!=='super_admin')abort_unless(auth()->user()->hasPermission('kyc.approve'),403);
        $user=$kyc->user;
        if($v['decision']==='approved'){
            if($user->email && !$user->email_verified_at)return back()->withErrors(['decision'=>'Email exists but is not verified. Complete identity verification before activating KYC.']);
            if($user->mobile && !$user->mobile_verified_at)return back()->withErrors(['decision'=>'Mobile exists but is not verified. Complete identity verification before activating KYC.']);
        }
        $kyc->update(['status'=>$v['decision'],'reason_id'=>$v['reason_id'] ?? null,'notes'=>$v['notes'] ?? null,'reviewed_by'=>auth()->id(),'reviewed_at'=>now()]);
        if($v['decision']==='approved')$user->update(['status'=>'active']);
        if(in_array($v['decision'],['rejected','resubmission_required'],true))$user->update(['status'=>'pending']);
        app(PluginRuntime::class)->doAction('kyc.reviewed',$kyc->fresh(),$user,$v['decision'],auth()->user());
        try{$notifications->userRegistration($user,'KYC_'.strtoupper($v['decision']),'KYC decision for '.$user->name.': '.$v['decision'].'. '.($v['notes'] ?? ''));}catch(\Throwable $e){report($e);}
        return back()->with('status','KYC decision recorded.');
    }
}
