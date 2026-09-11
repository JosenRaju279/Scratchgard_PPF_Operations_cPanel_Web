<?php
namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Plugins\PluginRuntime;

class TicketService
{
    public function create(array $data, User $actor, array $participantIds=[]): Ticket
    {
        return DB::transaction(function() use($data,$actor,$participantIds){
            $data['ticket_number']=$data['ticket_number'] ?? $this->nextNumber();
            $data['raised_by']=$data['raised_by'] ?? $actor->id;
            $data['last_activity_at']=now();
            $data=app(PluginRuntime::class)->applyFilters('ticket.create.data',$data,$actor,$participantIds);
            $ticket=Ticket::create($data);
            $ids=array_unique(array_filter(array_merge([$actor->id,$data['assigned_to'] ?? null],$participantIds)));
            foreach($ids as $id) $ticket->participants()->syncWithoutDetaching([$id=>['participant_role'=>'participant']]);
            $this->event($ticket,$actor,'TICKET_CREATED',null,$ticket->status,'Ticket created');
            DB::afterCommit(fn()=>app(PluginRuntime::class)->doAction('ticket.created',$ticket->fresh(),$actor));
            return $ticket;
        });
    }

    public function event(Ticket $ticket, User $actor, string $type, ?string $from, ?string $to, ?string $notes=null, array $meta=[]): void
    {
        TicketEvent::create(['ticket_id'=>$ticket->id,'actor_id'=>$actor->id,'event_type'=>$type,'from_status'=>$from,'to_status'=>$to,'notes'=>$notes,'metadata'=>$meta ?: null,'created_at'=>now()]);
        $ticket->update(['last_activity_at'=>now()]);
        DB::afterCommit(function() use($ticket,$type,$notes,$actor,$from,$to,$meta){$fresh=$ticket->fresh();try{app(SystemNotificationService::class)->ticketEvent($fresh,$type,ucwords(strtolower(str_replace('_',' ',$type))),$notes);}catch(\Throwable $e){report($e);}app(PluginRuntime::class)->doAction('ticket.event',$fresh,$type,$actor,$from,$to,$notes,$meta);app(PluginRuntime::class)->doAction('ticket.event.'.strtolower(str_replace('_','.',$type)),$fresh,$actor,$from,$to,$notes,$meta);});
    }

    private function nextNumber(): string
    {
        $prefix='SG-TKT-'.now()->format('ymd').'-';
        $last=Ticket::where('ticket_number','like',$prefix.'%')->lockForUpdate()->orderByDesc('id')->value('ticket_number');
        $seq=1;
        if($last && preg_match('/(\d{5})$/',$last,$m)) $seq=((int)$m[1])+1;
        return $prefix.str_pad((string)$seq,5,'0',STR_PAD_LEFT);
    }
}
