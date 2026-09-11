<?php
namespace App\Plugins;

use App\Models\Plugin;
use Illuminate\Support\Facades\Log;

class PluginRuntime
{
    private array $actions=[];
    private array $filters=[];
    private array $navigation=[];
    private array $widgets=[];
    private array $uiSlots=[];
    private array $uiTabs=[];
    private array $formFields=[];
    private array $tableColumns=[];
    private array $tableActions=[];
    private array $loaded=[];

    public function action(string $hook, callable $callback, int $priority=10, ?string $plugin=null): void
    {
        $this->actions[$hook][$priority][]=[$callback,$plugin];
    }

    public function filter(string $hook, callable $callback, int $priority=10, ?string $plugin=null): void
    {
        $this->filters[$hook][$priority][]=[$callback,$plugin];
    }

    public function doAction(string $hook, mixed ...$args): void
    {
        foreach($this->sorted($this->actions[$hook]??[]) as [$cb,$slug]){
            try{$cb(...$args);}catch(\Throwable $e){$this->fault($slug,$hook,$e);}
        }
    }

    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        foreach($this->sorted($this->filters[$hook]??[]) as [$cb,$slug]){
            try{$value=$cb($value,...$args);}catch(\Throwable $e){$this->fault($slug,$hook,$e);}
        }
        return $value;
    }

    public function navigation(string $plugin,string $id,string $label,string $url,?string $permission=null,array $roles=[],?callable $when=null): void
    {
        $this->navigation[$plugin.'.'.$id]=compact('plugin','id','label','url','permission','roles','when');
    }

    public function navigationFor($user,array $context=[]): array
    {
        return array_values(array_filter($this->navigation,fn($item)=>$this->visible($item,$user,$context)));
    }

    public function dashboardWidget(string $plugin,string $id,string $title,callable $renderer,?string $permission=null,array $roles=[],?callable $when=null,int $priority=10): void
    {
        $this->widgets[$plugin.'.'.$id]=compact('plugin','id','title','renderer','permission','roles','when','priority');
    }

    public function widgetsFor($user,array $context=[]): array
    {
        $out=[];$items=$this->widgets;
        uasort($items,fn($a,$b)=>($a['priority']<=>$b['priority']));
        foreach($items as $item){
            if(!$this->visible($item,$user,$context))continue;
            try{$item['html']=($item['renderer'])($user,$context);$out[]=$item;}catch(\Throwable $e){$this->fault($item['plugin'],'dashboard.widget',$e);}
        }
        return $out;
    }

    /**
     * Register HTML in a named core UI slot. Renderers receive ($context, $user).
     * Context is an associative array populated by the core screen, e.g. ['workOrder'=>$workOrder].
     */
    public function uiSlot(string $plugin,string $slot,string $id,callable $renderer,?string $permission=null,array $roles=[],?callable $when=null,int $priority=10): void
    {
        $key=$plugin.'.'.$slot.'.'.$id;
        $this->uiSlots[$slot][$priority][$key]=compact('plugin','slot','id','renderer','permission','roles','when','priority');
    }

    public function renderSlot(string $slot,array $context=[],$user=null): string
    {
        $html='';$user=$user?:auth()->user();
        foreach($this->sortedAssociative($this->uiSlots[$slot]??[]) as $item){
            if(!$this->visible($item,$user,$context))continue;
            try{$chunk=($item['renderer'])($context,$user);if($chunk!==null)$html.=(string)$chunk;}
            catch(\Throwable $e){$this->fault($item['plugin'],'ui.slot.'.$slot,$e);}
        }
        return $html;
    }

    /** Register a tab on a supported core screen. */
    public function uiTab(string $plugin,string $screen,string $id,string $label,callable $renderer,?string $permission=null,array $roles=[],?callable $when=null,int $priority=10): void
    {
        $key=$plugin.'.'.$screen.'.'.$id;
        $this->uiTabs[$screen][$priority][$key]=compact('plugin','screen','id','label','renderer','permission','roles','when','priority');
    }

    public function tabsFor(string $screen,array $context=[],$user=null): array
    {
        $out=[];$user=$user?:auth()->user();
        foreach($this->sortedAssociative($this->uiTabs[$screen]??[]) as $item){
            if(!$this->visible($item,$user,$context))continue;
            try{$item['html']=(string)($item['renderer'])($context,$user);$out[]=$item;}
            catch(\Throwable $e){$this->fault($item['plugin'],'ui.tab.'.$screen,$e);}
        }
        return $out;
    }

    /**
     * Register a declarative field for a supported form. Core screens render common field types.
     * Definition supports: label,type,text|email|number|date|textarea|select|checkbox,required,options,placeholder,help,default.
     */
    public function formField(string $plugin,string $form,string $key,array $definition,?string $permission=null,array $roles=[],?callable $when=null,int $priority=10): void
    {
        $id=$plugin.'.'.$form.'.'.$key;
        $this->formFields[$form][$priority][$id]=compact('plugin','form','key','definition','permission','roles','when','priority');
    }

    public function formFieldsFor(string $form,array $context=[],$user=null): array
    {
        $out=[];$user=$user?:auth()->user();
        foreach($this->sortedAssociative($this->formFields[$form]??[]) as $item){
            if($this->visible($item,$user,$context))$out[]=$item;
        }
        return $out;
    }

    /** Register a column on a supported table. Renderer receives ($row,$context,$user). */
    public function tableColumn(string $plugin,string $table,string $id,string $label,callable $renderer,?string $permission=null,array $roles=[],?callable $when=null,int $priority=10): void
    {
        $key=$plugin.'.'.$table.'.'.$id;
        $this->tableColumns[$table][$priority][$key]=compact('plugin','table','id','label','renderer','permission','roles','when','priority');
    }

    public function tableColumnsFor(string $table,array $context=[],$user=null): array
    {
        $out=[];$user=$user?:auth()->user();
        foreach($this->sortedAssociative($this->tableColumns[$table]??[]) as $item){if($this->visible($item,$user,$context))$out[]=$item;}
        return $out;
    }

    public function renderTableCell(array $column,mixed $row,array $context=[],$user=null): string
    {
        try{return (string)($column['renderer'])($row,$context,$user?:auth()->user());}
        catch(\Throwable $e){$this->fault($column['plugin'],'ui.table.'.$column['table'].'.'.$column['id'],$e);return '';}
    }

    /** Register row-level actions for supported tables. */
    public function tableAction(string $plugin,string $table,string $id,string $label,callable $url,?string $permission=null,array $roles=[],?callable $when=null,string $method='GET',int $priority=10): void
    {
        $key=$plugin.'.'.$table.'.'.$id;
        $this->tableActions[$table][$priority][$key]=compact('plugin','table','id','label','url','permission','roles','when','method','priority');
    }

    public function tableActionsFor(string $table,mixed $row,array $context=[],$user=null): array
    {
        $out=[];$user=$user?:auth()->user();$ctx=array_merge($context,['row'=>$row]);
        foreach($this->sortedAssociative($this->tableActions[$table]??[]) as $item){
            if(!$this->visible($item,$user,$ctx))continue;
            try{$item['url']=(string)($item['url'])($row,$context,$user);$out[]=$item;}
            catch(\Throwable $e){$this->fault($item['plugin'],'ui.table_action.'.$table.'.'.$item['id'],$e);}
        }
        return $out;
    }

    public function markLoaded(string $slug): void {$this->loaded[$slug]=true;}
    public function loaded(string $slug): bool {return isset($this->loaded[$slug]);}

    private function visible(array $item,$user,array $context=[]): bool
    {
        if(!empty($item['roles']) && !in_array($user?->role?->slug,$item['roles'],true)) return false;
        if(!empty($item['permission']) && !$user?->hasPermission($item['permission'])) return false;
        if(!empty($item['when'])){
            try{if(!($item['when'])($context,$user))return false;}
            catch(\Throwable $e){$this->fault($item['plugin']??null,'ui.visibility',$e);return false;}
        }
        return true;
    }

    private function sorted(array $priorities): array
    {
        ksort($priorities,SORT_NUMERIC);$out=[];foreach($priorities as $items)foreach($items as $item)$out[]=$item;return $out;
    }

    private function sortedAssociative(array $priorities): array
    {
        ksort($priorities,SORT_NUMERIC);$out=[];foreach($priorities as $items)foreach($items as $item)$out[]=$item;return $out;
    }

    private function fault(?string $slug,string $hook,\Throwable $e): void
    {
        Log::error('Scratchgard plugin hook failed',['plugin'=>$slug,'hook'=>$hook,'error'=>$e->getMessage()]);
        if($slug){try{Plugin::where('slug',$slug)->update(['last_error'=>substr($e->getMessage(),0,65000)]);}catch(\Throwable $ignore){}}
    }
}
