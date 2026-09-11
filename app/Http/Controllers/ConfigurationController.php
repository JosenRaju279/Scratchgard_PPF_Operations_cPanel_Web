<?php
namespace App\Http\Controllers;

use App\Models\EvidenceTemplate;
use App\Models\EvidenceTemplateSlot;
use App\Models\PricingRule;
use App\Models\Reason;
use App\Models\Zone;
use Illuminate\Http\Request;

class ConfigurationController extends Controller
{
    public function index()
    {
        return view('configuration.index',[
            'reasons'=>Reason::orderBy('category')->orderBy('sort_order')->get(),
            'templates'=>EvidenceTemplate::with('slots')->orderBy('package_code')->get(),
            'pricing'=>PricingRule::with('zone')->latest()->get(),
            'zones'=>Zone::where('status','active')->orderBy('name')->get(),
        ]);
    }

    public function reasonStore(Request $request)
    {
        $v=$request->validate(['category'=>'required|string|max:80','code'=>'required|string|max:80|unique:reasons,code','label'=>'required|string|max:160','comment_required'=>'nullable|boolean','evidence_required'=>'nullable|boolean','target_status'=>'nullable|string|max:80','payment_effect'=>'nullable|string|max:80']);
        Reason::create([...$v,'code'=>strtoupper($v['code']),'comment_required'=>$request->boolean('comment_required'),'evidence_required'=>$request->boolean('evidence_required'),'active'=>true]);
        return back()->with('status','Reason selector added.');
    }

    public function pricingStore(Request $request)
    {
        $v=$request->validate(['package_code'=>'required|string|max:80','zone_id'=>'nullable|exists:zones,id','vehicle_category'=>'nullable|string|max:80','amount'=>'required|numeric|min:0','currency'=>'required|string|size:3','effective_from'=>'nullable|date','effective_to'=>'nullable|date']);
        PricingRule::create([...$v,'package_code'=>strtoupper($v['package_code']),'currency'=>strtoupper($v['currency']),'active'=>true]);
        return back()->with('status','Pricing rule created.');
    }

    public function templateStore(Request $request)
    {
        $v=$request->validate(['name'=>'required|string|max:160','package_code'=>'required|string|max:80','version'=>'required|integer|min:1']);
        EvidenceTemplate::create(['name'=>$v['name'],'package_code'=>strtoupper($v['package_code']),'version'=>$v['version'],'active'=>true]);
        return back()->with('status','Evidence template created.');
    }

    public function slotStore(Request $request, EvidenceTemplate $template)
    {
        $v=$request->validate(['stage'=>'required|in:BEFORE,DURING,AFTER,REWORK_BEFORE,REWORK_AFTER','slot_code'=>'required|string|max:100','label'=>'required|string|max:180','closeup_hint'=>'nullable|string|max:180','mandatory'=>'nullable|boolean','camera_only'=>'nullable|boolean','location_required'=>'nullable|boolean','sort_order'=>'nullable|integer|min:0']);
        EvidenceTemplateSlot::create([
            'evidence_template_id'=>$template->id,'stage'=>$v['stage'],'slot_code'=>strtoupper($v['slot_code']),'label'=>$v['label'],
            'closeup_hint'=>$v['closeup_hint'] ?? null,'mandatory'=>$request->boolean('mandatory'),'camera_only'=>$request->boolean('camera_only'),
            'location_required'=>$request->boolean('location_required'),'sort_order'=>$v['sort_order'] ?? 0
        ]);
        return back()->with('status','Evidence slot added.');
    }
}
