<?php
namespace App\Services;

use App\Models\Assignment;
use App\Models\Payment;
use App\Models\Reason;
use App\Models\PricingRule;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkEvent;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Plugins\PluginRuntime;
use RuntimeException;

class WorkOrderService
{
    public function create(array $data, User $actor, array $pluginFields=[]): WorkOrder
    {
        $data=app(PluginRuntime::class)->applyFilters('work.create.data',$data,$actor,$pluginFields);
        app(PluginRuntime::class)->doAction('work.create.before',$data,$actor,$pluginFields);
        return DB::transaction(function () use ($data,$actor,$pluginFields) {
            $data['uuid'] = (string) Str::uuid();
            $data['tracking_token'] = $data['tracking_token'] ?? hash('sha256',Str::uuid()->toString().Str::random(48).microtime(true));
            $data['tracking_enabled'] = array_key_exists('tracking_enabled',$data) ? (bool)$data['tracking_enabled'] : true;
            $data['work_order_number'] = $data['work_order_number'] ?? $this->nextNumber($data['zone_code'] ?? 'GEN');
            $data['creation_source'] = $data['creation_source'] ?? 'manual';
            $data['status'] = $data['status'] ?? 'NEW';

            if (!array_key_exists('pricing_amount',$data) || $data['pricing_amount'] === null) {
                $rule = PricingRule::query()
                    ->where('package_code', strtoupper((string)($data['package_code'] ?? '')))
                    ->where('active', true)
                    ->where(function($q) use($data){ $q->whereNull('zone_id')->orWhere('zone_id',$data['zone_id'] ?? null); })
                    ->where(function($q){ $q->whereNull('effective_from')->orWhere('effective_from','<=',today()); })
                    ->where(function($q){ $q->whereNull('effective_to')->orWhere('effective_to','>=',today()); })
                    ->orderByRaw('CASE WHEN zone_id IS NULL THEN 1 ELSE 0 END')
                    ->latest('id')->first();
                if($rule){
                    $data['pricing_amount']=$rule->amount;
                    $data['pricing_currency']=$rule->currency;
                    $data['pricing_snapshot']=[
                        'pricing_rule_id'=>$rule->id,'package_code'=>$rule->package_code,'zone_id'=>$rule->zone_id,
                        'amount'=>$rule->amount,'currency'=>$rule->currency,'captured_at'=>now()->toIso8601String()
                    ];
                }
            }
            unset($data['zone_code']);
            $work = WorkOrder::create($data);
            $this->event($work,$actor,'WORK_CREATED',null,$work->status,null,'Work order created.');
            DB::afterCommit(function() use($work,$actor,$pluginFields){$runtime=app(PluginRuntime::class);$runtime->doAction('work.created',$work->fresh(),$actor);$runtime->doAction('work.created.fields',$work->fresh(),$pluginFields,$actor);});
            return $work;
        });
    }

    public function assignApplicator(WorkOrder $work, User $applicator, User $actor, ?Reason $reason=null, ?string $notes=null): WorkOrder
    {
        if (!in_array($work->status,['DELEGATED','ZONAL_ACCEPTED','APPLICATOR_ASSIGNED','CORRECTION_REQUIRED','REWORK_REQUIRED'],true)) {
            throw new RuntimeException('Work is not in an assignable state.');
        }

        return DB::transaction(function() use($work,$applicator,$actor,$reason,$notes) {
            $old = $work->applicator_id;
            if ($old) {
                Assignment::where('work_order_id',$work->id)->where('assignment_type','applicator')->whereNull('ended_at')->update(['ended_at'=>now()]);
            }

            Assignment::create([
                'work_order_id'=>$work->id,
                'assignment_type'=>'applicator',
                'from_user_id'=>$old,
                'to_user_id'=>$applicator->id,
                'actor_id'=>$actor->id,
                'reason_id'=>$reason?->id,
                'notes'=>$notes,
                'started_at'=>now(),
            ]);

            $from=$work->status;
            $work->update(['applicator_id'=>$applicator->id,'status'=>'APPLICATOR_ASSIGNED']);
            $this->event($work,$actor,$old?'APPLICATOR_REASSIGNED':'APPLICATOR_ASSIGNED',$from,'APPLICATOR_ASSIGNED',$reason,$notes,[
                'from_user_id'=>$old,'to_user_id'=>$applicator->id
            ]);
            return $work->fresh();
        });
    }

    public function transition(WorkOrder $work, User $actor, string $to, string $eventType, ?Reason $reason=null, ?string $message=null, array $meta=[]): WorkOrder
    {
        return DB::transaction(function() use($work,$actor,$to,$eventType,$reason,$message,$meta) {
            $to=(string)app(PluginRuntime::class)->applyFilters('work.transition.target',$to,$work,$actor,$eventType,$reason,$message,$meta);
            app(PluginRuntime::class)->doAction('work.transition.before',$work,$actor,$work->status,$to,$eventType,$reason,$message,$meta);
            $from=$work->status;
            $work->update(['status'=>$to]);
            $this->event($work,$actor,$eventType,$from,$to,$reason,$message,$meta);
            return $work->fresh();
        });
    }

    public function approve(WorkOrder $work, User $actor, ?Reason $reason=null, ?string $comments=null): WorkOrder
    {
        return DB::transaction(function() use($work,$actor,$reason,$comments) {
            app(PluginRuntime::class)->doAction('work.approval.before',$work,$actor,$reason,$comments);
            $from=$work->status;
            $work->update(['status'=>'APPROVED','approved_at'=>now()]);
            $this->event($work,$actor,'WORK_APPROVED',$from,'APPROVED',$reason,$comments);

            $mode=(string)Setting::getValue('payment_tracking_mode','approval_status');
            $defaults=['eligible_amount'=>$work->pricing_amount ?? 0,'currency'=>$work->pricing_currency ?? 'INR','status'=>'eligible'];
            if($mode==='approval_status'){
                $defaults=array_merge($defaults,[
                    'status'=>'paid','paid_amount'=>$work->pricing_amount ?? 0,'paid_at'=>now(),
                    'mode'=>'status_based_approval','reference'=>'AUTO-'.$work->work_order_number,'recorded_by'=>$actor->id,
                ]);
            }
            $defaults=app(PluginRuntime::class)->applyFilters('payment.approval.defaults',$defaults,$work,$actor);
            $payment=Payment::firstOrCreate(['work_order_id'=>$work->id],$defaults);
            DB::afterCommit(fn()=>app(PluginRuntime::class)->doAction('payment.eligible',$payment->fresh(),$work->fresh(),$actor));

            return $work->fresh();
        });
    }

    public function event(WorkOrder $work, User $actor, string $type, ?string $from, ?string $to, ?Reason $reason=null, ?string $message=null, array $meta=[]): void
    {
        WorkEvent::create([
            'work_order_id'=>$work->id,
            'actor_id'=>$actor->id,
            'event_type'=>$type,
            'from_status'=>$from,
            'to_status'=>$to,
            'reason_id'=>$reason?->id,
            'message'=>$message,
            'metadata'=>$meta ?: null,
            'created_at'=>now(),
        ]);
        DB::afterCommit(function() use($work,$type,$message,$from,$to,$actor,$meta){
            $fresh=$work->fresh();
            try{ app(SystemNotificationService::class)->workEvent($fresh,$type,ucwords(strtolower(str_replace('_',' ',$type))),$message); }catch(\Throwable $e){ report($e); }
            $runtime=app(PluginRuntime::class);$runtime->doAction('work.event',$fresh,$type,$actor,$from,$to,$message,$meta);$runtime->doAction('work.event.'.strtolower(str_replace('_','.',$type)),$fresh,$actor,$from,$to,$message,$meta);
            if($from!==$to)$runtime->doAction('work.status.changed',$fresh,$from,$to,$actor,$type);
        });
    }

    private function nextNumber(string $zoneCode): string
    {
        $prefix='SG-'.strtoupper(preg_replace('/[^A-Z0-9]/i','',$zoneCode) ?: 'GEN').'-'.now()->format('ymd').'-';
        $last=WorkOrder::where('work_order_number','like',$prefix.'%')->lockForUpdate()->orderByDesc('id')->value('work_order_number');
        $seq=1;
        if ($last && preg_match('/(\d{5})$/',$last,$m)) $seq=((int)$m[1])+1;
        return $prefix.str_pad((string)$seq,5,'0',STR_PAD_LEFT);
    }
}
