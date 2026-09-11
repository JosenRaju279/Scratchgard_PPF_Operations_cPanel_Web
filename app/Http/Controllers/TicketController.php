<?php
namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\TicketService;
use App\Services\SystemNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Plugins\PluginRuntime;

class TicketController extends Controller
{
    public function index()
    {
        $u=auth()->user();$q=Ticket::with(['workOrder','raiser','assignee'])->latest('last_activity_at');
        if($u->role?->slug!=='super_admin'&&!$u->hasPermission('tickets.manage'))$q->where(function($x)use($u){$x->where('raised_by',$u->id)->orWhere('assigned_to',$u->id)->orWhereHas('participants',fn($p)=>$p->where('users.id',$u->id));});
        return view('tickets.index',['tickets'=>$q->paginate(30),'workOrders'=>WorkOrder::latest()->limit(100)->get(),'users'=>User::where('status','active')->orderBy('name')->get()]);
    }
    public function store(Request $request, TicketService $service)
    {
        $v=$request->validate(['work_order_id'=>'nullable|exists:work_orders,id','assigned_to'=>'nullable|exists:users,id','category'=>'required|in:general,complaint,quality,payment,kyc,technical,operations','subject'=>'required|string|max:190','description'=>'required|string|max:5000','priority'=>'required|in:low,normal,high,critical']);
        $ticket=$service->create($v,auth()->user(),array_filter([$v['assigned_to'] ?? null]));
        return redirect()->route('tickets.show',$ticket)->with('status','Ticket created: '.$ticket->ticket_number);
    }
    public function show(Ticket $ticket){$this->access($ticket);$ticket->load(['workOrder.vehicle','raiser','assignee','participants','messages.sender','events']);return view('tickets.show',['ticket'=>$ticket,'users'=>User::where('status','active')->orderBy('name')->get()]);}
    public function message(Request $request, Ticket $ticket, SystemNotificationService $notifications){
        $this->access($ticket);
        $v=$request->validate(['message'=>'required|string|max:5000','internal_only'=>'nullable|boolean','attachments'=>'nullable|array|max:5','attachments.*'=>'file|max:10240']);
        $stored=[];$disk=env('EVIDENCE_DISK',env('FILESYSTEM_DISK','local'));
        foreach($request->file('attachments',[]) as $file){
            $path=Storage::disk($disk)->putFile('tickets/'.$ticket->ticket_number,$file);
            $stored[]=['disk'=>$disk,'path'=>$path,'name'=>$file->getClientOriginalName(),'mime'=>$file->getMimeType(),'size'=>$file->getSize()];
        }
        $message=TicketMessage::create(['ticket_id'=>$ticket->id,'sender_id'=>auth()->id(),'message'=>$v['message'],'attachments'=>$stored ?: null,'internal_only'=>auth()->user()->role?->is_internal?$request->boolean('internal_only'):false]);
        $ticket->update(['last_activity_at'=>now()]);app(PluginRuntime::class)->doAction('ticket.reply.created',$ticket->fresh(),$message,(array)$request->input('plugin_fields',[]),auth()->user());try{$notifications->ticketEvent($ticket->fresh(),'TICKET_REPLY','New ticket reply',$v['message']);}catch(\Throwable $e){report($e);}return back()->with('status','Reply added.');
    }
    public function attachment(Ticket $ticket, TicketMessage $message, int $index)
    {
        $this->access($ticket); abort_unless((int)$message->ticket_id===(int)$ticket->id,404);
        $items=$message->attachments ?? []; abort_unless(isset($items[$index]),404); $a=$items[$index];
        $disk=$a['disk'] ?? 'local';$path=$a['path'] ?? null;abort_unless($path && Storage::disk($disk)->exists($path),404);
        if($disk==='s3') return redirect()->away(Storage::disk('s3')->temporaryUrl($path,now()->addMinutes(10),['ResponseContentDisposition'=>'attachment; filename="'.basename($a['name'] ?? $path).'"']));
        return Storage::disk($disk)->download($path,$a['name'] ?? basename($path));
    }
    public function status(Request $request, Ticket $ticket, TicketService $service)
    {
        abort_unless(auth()->user()->role?->slug==='super_admin'||auth()->user()->hasPermission('tickets.manage'),403);$v=$request->validate(['status'=>'required|in:open,in_progress,waiting_user,resolved,closed,reopened','assigned_to'=>'nullable|exists:users,id','notes'=>'nullable|string|max:2000']);$from=$ticket->status;$ticket->update(['status'=>$v['status'],'assigned_to'=>$v['assigned_to'] ?? $ticket->assigned_to,'closed_at'=>$v['status']==='closed'?now():null]);if(!empty($v['assigned_to']))$ticket->participants()->syncWithoutDetaching([$v['assigned_to']=>['participant_role'=>'assignee']]);$service->event($ticket,auth()->user(),'STATUS_CHANGED',$from,$v['status'],$v['notes'] ?? null,['assigned_to'=>$v['assigned_to'] ?? null]);return back()->with('status','Ticket updated.');
    }
    private function access(Ticket $ticket): void{$u=auth()->user();if($u->role?->slug==='super_admin'||$u->hasPermission('tickets.manage'))return;$ok=$ticket->raised_by===$u->id||$ticket->assigned_to===$u->id||$ticket->participants()->where('users.id',$u->id)->exists();abort_unless($ok,403);}
}
