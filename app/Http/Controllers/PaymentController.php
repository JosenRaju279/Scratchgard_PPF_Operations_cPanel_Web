<?php
namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reason;
use App\Models\WorkEvent;
use App\Services\SystemNotificationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Plugins\PluginRuntime;

class PaymentController extends Controller
{
    public function index(){return view('payments.index',['payments'=>Payment::with('workOrder.vehicle')->latest()->paginate(40),'revertReasons'=>Reason::where('category','payment')->where('active',true)->get()]);}
    public function markPaid(Request $request, Payment $payment){abort_unless(auth()->user()->role?->slug==='super_admin'||auth()->user()->hasPermission('payment.manage'),403);abort_if($payment->status==='paid',422,'Payment is already paid.');abort_if($payment->status==='held',422,'Release the payment hold before marking paid.');$v=$request->validate(['paid_amount'=>'required|numeric|min:0','paid_at'=>'required|date','mode'=>'required|string|max:60','reference'=>'nullable|string|max:160']);$from=$payment->status;$payment->update([...$v,'previous_status'=>$from,'status'=>'paid','recorded_by'=>auth()->id()]);$this->event($payment,'PAYMENT_PAID',$from,'paid','Payment recorded');return back()->with('status','Payment marked paid.');}
    public function revert(Request $request, Payment $payment){abort_unless(auth()->user()->role?->slug==='super_admin'||auth()->user()->hasPermission('payment.revert'),403);$v=$request->validate(['target_status'=>'required|in:eligible,held,reversed','reason_id'=>'required|exists:reasons,id','notes'=>'required|string|max:2000']);$from=$payment->status;$payment->update(['previous_status'=>$from,'status'=>$v['target_status'],'reverted_at'=>now(),'reverted_by'=>auth()->id(),'revert_reason_id'=>$v['reason_id'],'revert_notes'=>$v['notes'],'held_at'=>$v['target_status']==='held'?now():null,'hold_reason_id'=>$v['target_status']==='held'?$v['reason_id']:null]);$this->event($payment,'PAYMENT_STATUS_REVERTED',$from,$v['target_status'],$v['notes']);return back()->with('status','Payment status changed under privileged payment.revert permission. Audit history preserved.');}
    public function export(): StreamedResponse{abort_unless(auth()->user()->role?->slug==='super_admin'||auth()->user()->hasPermission('payment.view'),403);$filename='scratchgard-payments-'.now()->format('Ymd-His').'.csv';return response()->streamDownload(function(){$out=fopen('php://output','w');fputcsv($out,['Work Order','VIN','Status','Eligible Amount','Paid Amount','Paid At','Mode','Reference','Previous Status']);Payment::with('workOrder.vehicle')->orderBy('id')->chunk(500,function($rows)use($out){foreach($rows as $p)fputcsv($out,[$p->workOrder?->work_order_number,$p->workOrder?->vehicle?->vin,$p->status,$p->eligible_amount,$p->paid_amount,optional($p->paid_at)->toDateTimeString(),$p->mode,$p->reference,$p->previous_status]);});fclose($out);},$filename,['Content-Type'=>'text/csv']);}
    private function event(Payment $p,string $type,?string $from,?string $to,?string $message): void{WorkEvent::create(['work_order_id'=>$p->work_order_id,'actor_id'=>auth()->id(),'event_type'=>$type,'from_status'=>$from,'to_status'=>$to,'message'=>$message,'metadata'=>['payment_id'=>$p->id],'created_at'=>now()]);try{app(SystemNotificationService::class)->workEvent($p->workOrder,$type,ucwords(strtolower(str_replace('_',' ',$type))),$message);}catch(\Throwable $e){report($e);}app(PluginRuntime::class)->doAction('payment.event',$p->fresh(),$type,$from,$to,auth()->user(),$message);app(PluginRuntime::class)->doAction('payment.event.'.strtolower(str_replace('_','.',$type)),$p->fresh(),auth()->user(),$message);}
}
