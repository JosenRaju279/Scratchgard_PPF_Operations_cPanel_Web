<?php
namespace App\Plugins;

use App\Models\Permission;
use App\Models\Plugin;
use App\Models\PluginSetting;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\View;

class PluginContext
{
    public function __construct(public Plugin $plugin, private PluginRuntime $runtime){}
    public function slug(): string{return $this->plugin->slug;}
    public function path(string $path=''): string{return base_path($this->plugin->path.($path?'/'.ltrim($path,'/') : ''));}
    public function on(string $hook,callable $callback,int $priority=10): void{$this->runtime->action($hook,$callback,$priority,$this->slug());}
    public function filter(string $hook,callable $callback,int $priority=10): void{$this->runtime->filter($hook,$callback,$priority,$this->slug());}
    public function setting(string $key,mixed $default=null): mixed{return PluginSetting::where('plugin_id',$this->plugin->id)->where('key',$key)->first()?->decodedValue($default)??$default;}
    public function permission(string $key,string $name,?string $group=null): string
    {
        $slug='plugin.'.$this->slug().'.'.preg_replace('/[^a-z0-9_.-]/','',strtolower($key));
        Permission::firstOrCreate(['slug'=>$slug],['name'=>$name,'group'=>$group?:'plugin:'.$this->slug()]);
        $items=array_values(array_unique(array_merge($this->plugin->registered_permissions??[],[$slug])));
        $this->plugin->forceFill(['registered_permissions'=>$items])->saveQuietly();
        return $slug;
    }
    public function navigation(string $id,string $label,string $url,?string $permission=null,array $roles=[],?callable $when=null): void{$this->runtime->navigation($this->slug(),$id,$label,$url,$permission,$roles,$when);}
    public function dashboardWidget(string $id,string $title,callable $renderer,?string $permission=null,array $roles=[],?callable $when=null,int $priority=10): void{$this->runtime->dashboardWidget($this->slug(),$id,$title,$renderer,$permission,$roles,$when,$priority);}

    public function uiSlot(string $slot,string $id,callable $renderer,?string $permission=null,array $roles=[],?callable $when=null,int $priority=10): void
    {$this->runtime->uiSlot($this->slug(),$slot,$id,$renderer,$permission,$roles,$when,$priority);}

    public function uiTab(string $screen,string $id,string $label,callable $renderer,?string $permission=null,array $roles=[],?callable $when=null,int $priority=10): void
    {$this->runtime->uiTab($this->slug(),$screen,$id,$label,$renderer,$permission,$roles,$when,$priority);}

    public function formField(string $form,string $key,array $definition,?string $permission=null,array $roles=[],?callable $when=null,int $priority=10): void
    {$this->runtime->formField($this->slug(),$form,$key,$definition,$permission,$roles,$when,$priority);}

    public function tableColumn(string $table,string $id,string $label,callable $renderer,?string $permission=null,array $roles=[],?callable $when=null,int $priority=10): void
    {$this->runtime->tableColumn($this->slug(),$table,$id,$label,$renderer,$permission,$roles,$when,$priority);}

    public function tableAction(string $table,string $id,string $label,callable $url,?string $permission=null,array $roles=[],?callable $when=null,string $method='GET',int $priority=10): void
    {$this->runtime->tableAction($this->slug(),$table,$id,$label,$url,$permission,$roles,$when,$method,$priority);}

    public function webRoutes(callable $routes,array $middleware=['web','installed','auth']): void
    {
        Route::middleware($middleware)->prefix('extensions/'.$this->slug())->name('ext.'.$this->slug().'.')->group($routes);
    }
    public function apiRoutes(callable $routes,array $middleware=['api']): void
    {
        Route::middleware($middleware)->prefix('api/extensions/'.$this->slug())->name('api.ext.'.$this->slug().'.')->group($routes);
    }
    public function views(string $relative='resources/views'): void
    {
        $dir=$this->path($relative);if(is_dir($dir))View::addNamespace('plugin-'.$this->slug(),$dir);
    }
    public function schedule(callable $callback,string $cron,string $name): void
    {
        Schedule::call($callback)->cron($cron)->name('plugin-'.$this->slug().'-'.$name)->withoutOverlapping();
    }
    public function asset(string $relative): string{return asset('plugins/'.$this->slug().'/'.ltrim($relative,'/'));}
    public function app(){return app();}
}
