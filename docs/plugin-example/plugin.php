<?php
use App\Contracts\ScratchgardPlugin;
use App\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;

return new class implements ScratchgardPlugin {
    public function boot(PluginContext $p): void {
        $permission=$p->permission('report.view','View example plugin report');
        $p->on('work.created',fn($work,$actor)=>logger()->info('Example plugin saw new work',['work'=>$work->work_order_number]));
        $p->filter('notification.message',fn($pair)=>$pair);
        $p->webRoutes(function() use($p,$permission){Route::get('/report',fn()=>view('plugin-'.$p->slug().'::report'))->middleware('permission:'.$permission)->name('report');});
        $p->navigation('report','Example report',url('/extensions/'.$p->slug().'/report'),$permission);
        $p->dashboardWidget('summary','Example extension',fn()=>'<p>'.e($p->setting('label','Example extension is active')).'</p>',$permission);
        $p->uiSlot('work.show.after_vehicle','example-card',fn(array $ctx)=>'<div class="card"><h2>Example plugin card</h2><p>Injected into '.e($ctx['workOrder']->work_order_number??'work').' without editing core.</p></div>',$permission);
        $p->uiTab('work.show','example-tab','Example tab',fn(array $ctx)=>'<div class="plugin-ui-card">Plugin tab for '.e($ctx['workOrder']->work_order_number??'—').'</div>',$permission);
        $p->tableColumn('work_orders','example','Example',fn($work)=>'<span class="badge">Plugin</span>',$permission);
        $p->schedule(fn()=>logger()->debug('Example plugin scheduled tick'),'*/30 * * * *','tick');
        $p->views();
    }
    public function activate(PluginContext $p): void {}
    public function deactivate(PluginContext $p): void {}
    public function uninstall(PluginContext $p,bool $purgeData=false): void {}
    public function update(PluginContext $p,?string $fromVersion,string $toVersion): void {}
    public function health(PluginContext $p): array {return ['ok'=>true,'message'=>'Example extension loaded'];}
};
