<?php
namespace App\Services;

use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use App\Plugins\PluginRuntime;

class SystemNotificationService
{
    public function userRegistration(User $user, string $event='USER_REGISTERED', ?string $message=null): void
    {
        if(!Setting::getValue('email_notifications_enabled',true)) return;
        $recipients=collect([$user]);
        if(Setting::getValue('email_notify_super_admins',true)) $recipients=$recipients->merge($this->activeRoleUsers('super_admin'));
        if($user->primary_zone_id) $recipients=$recipients->merge(User::whereHas('role',fn($q)=>$q->where('slug','zonal_manager'))->where(function($q)use($user){$q->where('primary_zone_id',$user->primary_zone_id)->orWhereHas('zones',fn($z)=>$z->where('zones.id',$user->primary_zone_id));})->where('status','active')->get());
        $this->sendUsers($recipients,$event,'Scratchgard account update', $message ?: "Scratchgard account event: {$event}. User: {$user->name} (@{$user->username}).");
    }

    public function workEvent(WorkOrder $work, string $eventType, string $headline, ?string $message=null, array $extraUsers=[]): void
    {
        if(!Setting::getValue('email_notifications_enabled',true)) return;
        $work->loadMissing(['vehicle','showroom','zonalManager','applicator','verifier']);
        $recipients=collect([$work->zonalManager,$work->applicator,$work->verifier])->filter();
        foreach($extraUsers as $u) if($u instanceof User) $recipients->push($u);
        if(Setting::getValue('email_notify_work_delegators',true)) $recipients=$recipients->merge($this->activeRoleUsers('work_delegator'));
        if(Setting::getValue('email_notify_super_admins',true)) $recipients=$recipients->merge($this->activeRoleUsers('super_admin'));
        $tracker=$work->tracking_enabled && $work->tracking_token ? route('tracking.show',$work->tracking_token) : null;
        $private=route('work-orders.show',$work);
        $body=$message ?: $headline;
        $body.="\n\nWork Order: {$work->work_order_number}\nStatus: {$work->status}";
        if($work->vehicle) $body.="\nVehicle: ".trim(($work->vehicle->make ?? '').' '.($work->vehicle->model ?? ''))."\nVIN: ".($work->vehicle->vin ?? '');
        if($work->showroom) $body.="\nShowroom: {$work->showroom->name}";
        $body.="\n\nOpen work (login required): {$private}";
        if($tracker) $body.="\nPublic tracker: {$tracker}";
        $this->sendUsers($recipients,$eventType,"Scratchgard · {$headline} · {$work->work_order_number}",$body,$work);
    }

    public function ticketEvent(\App\Models\Ticket $ticket, string $eventType, string $headline, ?string $message=null): void
    {
        if(!Setting::getValue('email_notifications_enabled',true)) return;
        $ticket->loadMissing(['raiser','assignee','participants','workOrder']);
        $recipients=collect([$ticket->raiser,$ticket->assignee])->filter()->merge($ticket->participants);
        if(Setting::getValue('email_notify_super_admins',true)) $recipients=$recipients->merge($this->activeRoleUsers('super_admin'));
        $body=($message ?: $headline)."\n\nTicket: {$ticket->ticket_number}\nStatus: {$ticket->status}\nOpen: ".route('tickets.show',$ticket);
        $this->sendUsers($recipients,$eventType,"Scratchgard · {$headline} · {$ticket->ticket_number}",$body,$ticket->workOrder);
    }

    public function direct(User $user, string $eventType, string $subject, string $body, ?WorkOrder $work=null): void
    {
        $this->sendUsers(collect([$user]),$eventType,$subject,$body,$work);
    }

    private function activeRoleUsers(string $slug): Collection
    {
        return User::whereHas('role',fn($q)=>$q->where('slug',$slug))->where('status','active')->whereNotNull('email')->get();
    }

    private function sendUsers(Collection $users, string $eventType, string $subject, string $body, ?WorkOrder $work=null): void
    {
        $users=app(PluginRuntime::class)->applyFilters('notification.recipients',$users,$eventType,$subject,$body,$work);
        $users=collect($users)->filter(fn($u)=>$u instanceof User && $u->email)->unique('email')->values();
        foreach($users as $user) $this->sendEmail($user,$eventType,$subject,$body,$work);
    }

    private function sendEmail(User $user, string $eventType, string $subject, string $body, ?WorkOrder $work=null): void
    {
        [$subject,$body]=app(PluginRuntime::class)->applyFilters('notification.message',[$subject,$body],$user,$eventType,$work);
        app(PluginRuntime::class)->doAction('notification.before_send',$user,$eventType,$subject,$body,$work);
        $log=NotificationLog::create(['user_id'=>$user->id,'work_order_id'=>$work?->id,'event_type'=>$eventType,'email'=>$user->email,'subject'=>$subject,'status'=>'pending']);
        try{
            Mail::raw($body,function($m)use($user,$subject){$m->to($user->email)->subject($subject);});
            $log->update(['status'=>'sent','sent_at'=>now()]);app(PluginRuntime::class)->doAction('notification.sent',$log->fresh(),$user,$work);
        }catch(\Throwable $e){
            $log->update(['status'=>'failed','error'=>substr($e->getMessage(),0,65000)]);
            app(PluginRuntime::class)->doAction('notification.failed',$log->fresh(),$user,$e,$work);report($e);
        }
    }
}
