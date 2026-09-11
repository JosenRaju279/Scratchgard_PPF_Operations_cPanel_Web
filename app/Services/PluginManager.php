<?php
namespace App\Services;

use App\Contracts\ScratchgardPlugin;
use App\Models\Permission;
use App\Models\Plugin;
use App\Models\PluginActivityLog;
use App\Models\PluginSetting;
use App\Plugins\PluginContext;
use App\Plugins\PluginRuntime;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionFunction;
use RuntimeException;
use ZipArchive;

class PluginManager
{
    public function __construct(private PluginRuntime $runtime){}

    public function install(string $zipPath, ?int $actorId=null): Plugin
    {
        if(!class_exists(ZipArchive::class)) throw new RuntimeException('PHP Zip extension is required for plugin uploads.');
        $zip=new ZipArchive();
        if($zip->open($zipPath)!==true) throw new RuntimeException('Invalid plugin ZIP.');
        try{
            if($zip->numFiles>2500) throw new RuntimeException('Plugin ZIP contains too many files.');
            $manifestRaw=$zip->getFromName('plugin.json');
            if(!$manifestRaw) throw new RuntimeException('Plugin ZIP must contain plugin.json at its root.');
            $manifest=json_decode($manifestRaw,true,512,JSON_THROW_ON_ERROR);
            $this->validateManifest($manifest);
            $slug=$this->safeSlug($manifest['slug']);
            $existing=Plugin::where('slug',$slug)->first();
            $fromVersion=$existing?->version;
            $wasEnabled=(bool)$existing?->enabled;
            $this->assertCompatible($manifest,$existing);
            $this->assertZipSafe($zip);

            $staging=storage_path('app/plugin-staging/'.Str::uuid());
            File::ensureDirectoryExists($staging,0755,true);
            $total=0;
            for($i=0;$i<$zip->numFiles;$i++){
                $name=$zip->getNameIndex($i);if(!$name)continue;
                if(str_contains($name,'../')||str_starts_with($name,'/')||str_contains($name,"\0"))throw new RuntimeException('Unsafe plugin ZIP path: '.$name);
                $stat=$zip->statIndex($i);$total+=(int)($stat['size']??0);if($total>80*1024*1024)throw new RuntimeException('Expanded plugin size exceeds 80 MB safety limit.');
                $target=$staging.'/'.$name;
                if(str_ends_with($name,'/')){File::ensureDirectoryExists($target,0755,true);continue;}
                File::ensureDirectoryExists(dirname($target),0755,true);file_put_contents($target,$zip->getFromIndex($i));
            }
            if(!is_file($staging.'/plugin.php')) throw new RuntimeException('Plugin ZIP must contain plugin.php at its root.');

            $dest=base_path('plugins/'.$slug);
            $backup=null;
            if(File::isDirectory($dest)){
                $backup=storage_path('app/plugin-backups/'.$slug.'-'.now()->format('YmdHis'));
                File::ensureDirectoryExists(dirname($backup),0755,true);File::copyDirectory($dest,$backup);File::deleteDirectory($dest);
            }
            File::ensureDirectoryExists(dirname($dest),0755,true);File::moveDirectory($staging,$dest,true);

            $payload=[
                'name'=>$manifest['name'],'description'=>$manifest['description']??null,'author'=>$manifest['author']??null,'homepage'=>$manifest['homepage']??null,
                'version'=>$manifest['version'],'previous_version'=>$fromVersion,'path'=>'plugins/'.$slug,'enabled'=>$existing?$wasEnabled:false,
                'manifest'=>$manifest,'settings_schema'=>$manifest['settings']??[],'checksum'=>$this->directoryChecksum($dest),'last_error'=>null,
                'installed_by'=>$actorId,'installed_at'=>$existing?->installed_at??now(),
            ];
            $plugin=Plugin::updateOrCreate(['slug'=>$slug],$payload);
            $this->registerManifestPermissions($plugin);
            $this->seedSettingDefaults($plugin);
            $this->activity($plugin,$actorId,$existing?'updated':'installed',($existing?'Updated':'Installed').' plugin '.$plugin->name.'.',['from_version'=>$fromVersion,'to_version'=>$plugin->version,'backup'=>$backup]);

            if($existing && $wasEnabled){
                try{$this->runMigrations($plugin);$this->invokeLifecycle($plugin,'update',$fromVersion,$plugin->version);$this->publishAssets($plugin);}
                catch(\Throwable $e){$plugin->update(['enabled'=>false,'last_error'=>$e->getMessage(),'disabled_at'=>now()]);throw new RuntimeException('Plugin files updated, but activation was disabled because update tasks failed: '.$e->getMessage(),0,$e);}
            }
            return $plugin->fresh();
        }finally{$zip->close();}
    }

    public function enable(Plugin $plugin,?int $actorId=null): Plugin
    {
        $this->assertCompatible($plugin->manifest??[],$plugin);$this->assertDependencies($plugin);
        try{
            $this->runMigrations($plugin);$this->publishAssets($plugin);$this->invokeLifecycle($plugin,'activate');
            $plugin->update(['enabled'=>true,'activated_at'=>now(),'disabled_at'=>null,'last_error'=>null]);
            $this->activity($plugin,$actorId,'enabled','Plugin enabled.');return $plugin->fresh();
        }catch(\Throwable $e){$plugin->update(['enabled'=>false,'last_error'=>substr($e->getMessage(),0,65000),'disabled_at'=>now()]);$this->activity($plugin,$actorId,'enable_failed',$e->getMessage());throw $e;}
    }

    public function disable(Plugin $plugin,?int $actorId=null): Plugin
    {
        if($plugin->enabled){try{$this->invokeLifecycle($plugin,'deactivate');}catch(\Throwable $e){$plugin->update(['last_error'=>substr($e->getMessage(),0,65000)]);}}
        $plugin->update(['enabled'=>false,'disabled_at'=>now()]);$this->activity($plugin,$actorId,'disabled','Plugin disabled.');return $plugin->fresh();
    }

    public function uninstall(Plugin $plugin,?int $actorId=null,bool $purgeData=false): void
    {
        if($plugin->enabled)$this->disable($plugin,$actorId);
        try{$this->invokeLifecycle($plugin,'uninstall',$purgeData);}catch(\Throwable $e){if($purgeData)throw $e;}
        if($purgeData){foreach($plugin->registered_permissions??[] as $slug)Permission::where('slug',$slug)->delete();}
        $path=base_path($plugin->path);$assetPath=public_path('plugins/'.$plugin->slug);
        $this->activity($plugin,$actorId,'uninstalled','Plugin files removed.',['purge_data'=>$purgeData]);
        $plugin->delete();if(File::isDirectory($path))File::deleteDirectory($path);if(File::isDirectory($assetPath))File::deleteDirectory($assetPath);
    }

    public function saveSettings(Plugin $plugin,array $input,?int $actorId=null): void
    {
        foreach($plugin->settings_schema??[] as $field){
            $key=(string)($field['key']??'');if(!$key)continue;$type=$field['type']??'text';$secret=(bool)($field['secret']??false);
            $value=$input[$key]??null;
            if($type==='boolean')$value=(bool)$value;
            elseif($type==='number'&&$value!=='')$value=(float)$value;
            elseif($type==='multiselect')$value=array_values((array)$value);
            elseif(is_string($value))$value=trim($value);
            if($secret && ($value===null||$value==='')) continue;
            PluginSetting::put($plugin,$key,$value,$secret);
        }
        $this->activity($plugin,$actorId,'settings_updated','Plugin settings updated.');$this->runtime->doAction('plugin.settings.updated',$plugin);
    }

    public function buildStarterPackage(array $input): string
    {
        if(!class_exists(ZipArchive::class)) throw new RuntimeException('PHP Zip extension is required to build a starter plugin ZIP.');
        $name=trim((string)($input['name']??'Scratchgard Custom Extension'));
        $slug=$this->safeSlug((string)($input['slug']??Str::slug($name)));
        $description=trim((string)($input['description']??'Custom Scratchgard extension.'));
        $author=trim((string)($input['author']??'Scratchgard Developer'));
        $permissionKey='feature.view';
        $manifest=[
            'name'=>$name,'slug'=>$slug,'version'=>'1.0.0','description'=>$description,'author'=>$author,
            'requires'=>['scratchgard'=>'>=2.6.0','php'=>'>=8.3'],
            'permissions'=>[['key'=>$permissionKey,'name'=>'View '.$name]],
            'settings'=>[['key'=>'enabled_feature','label'=>'Enable extension feature','type'=>'boolean','default'=>true]],
        ];
        $pluginPhp=<<<'PHP'
<?php
use App\Contracts\ScratchgardPlugin;
use App\Plugins\PluginContext;
use Illuminate\Support\Facades\Route;

return new class implements ScratchgardPlugin {
    public function boot(PluginContext $p): void
    {
        $view=$p->permission('feature.view','View custom extension');
        $p->views();

        // WordPress-style core screen injection: card on Work Order page.
        $p->uiSlot('work.show.after_vehicle','summary-card',function(array $ctx,$user) use ($p) {
            $work=$ctx['workOrder']??null;
            if(!$work)return '';
            return view('plugin-'.$p->slug().'::work-card',compact('work'))->render();
        },$view);

        // Add a tab/section on Work Order screen.
        $p->uiTab('work.show','extension-tab','Extension',function(array $ctx) {
            $work=$ctx['workOrder']??null;
            return '<div class="plugin-ui-card"><strong>Custom tab</strong><p>Work: '.e($work?->work_order_number??'—').'</p></div>';
        },$view);

        // Add a column to Work Order list.
        $p->tableColumn('work_orders','extension-status','Extension',function($work) {
            return '<span class="badge">Ready</span>';
        },$view);

        // Add a row action. Protect the destination route with the same permission.
        $p->tableAction('work_orders','extension-view','Extension',fn($work)=>url('/extensions/'.$p->slug().'/work/'.$work->id),$view);

        // Add a custom profile field. Plugin should validate/store it using a hook or its own endpoint.
        $p->formField('user.profile','custom_reference',[
            'label'=>'Custom reference','type'=>'text','placeholder'=>'Extension-defined value',
            'help'=>'Example plugin field. Store it in plugin-owned data using a hook or endpoint.'
        ],$view);

        // Dashboard + navigation.
        $p->dashboardWidget('summary','Custom extension',fn()=>'<p>Extension is active.</p>',$view);
        $p->navigation('home','Custom extension',url('/extensions/'.$p->slug()),$view);

        // Plugin routes.
        $p->webRoutes(function() use ($p,$view) {
            Route::get('/',fn()=>view('plugin-'.$p->slug().'::home'))->middleware('permission:'.$view)->name('home');
            Route::get('/work/{workOrder}',fn(\App\Models\WorkOrder $workOrder)=>'<h1>'.e($workOrder->work_order_number).'</h1>')->middleware('permission:'.$view)->name('work');
        });

        // Business hooks.
        $p->on('work.created',function($work,$actor){ /* sync/integration/logging */ });
        $p->filter('notification.recipients',fn($users,$eventType)=>$users);

        // Scheduler uses Scratchgard's existing cPanel Laravel cron.
        $p->schedule(fn()=>null,'0 * * * *','hourly-example');
    }
    public function activate(PluginContext $p): void {}
    public function deactivate(PluginContext $p): void {}
    public function uninstall(PluginContext $p,bool $purgeData=false): void {}
    public function update(PluginContext $p,?string $fromVersion,string $toVersion): void {}
    public function health(PluginContext $p): array {return ['ok'=>true,'message'=>'Starter extension healthy'];}
};
PHP;
        $readme="# {$name}\n\nGenerated by Scratchgard Plugin Builder Helper.\n\n1. Review plugin.json and plugin.php.\n2. Add plugin-owned migrations/views/assets if needed.\n3. ZIP root must contain plugin.json and plugin.php.\n4. Install from Super Admin → Plugins. New installs remain disabled until explicitly enabled.\n5. Use UI slots/hooks instead of modifying Scratchgard core files.\n";
        $home="@extends('layouts.app')\n@section('title','".addslashes($name)."')\n@section('content')\n<div class=\"page-head\"><div><p class=\"eyebrow\">Extension</p><h1>".htmlspecialchars($name,ENT_QUOTES)."</h1></div></div>\n<div class=\"card\"><p>Starter plugin page is working.</p></div>\n@endsection\n";
        $workCard='<div class="card"><div class="card-head"><div><h2>'.htmlspecialchars($name,ENT_QUOTES).'</h2><p class="muted">Injected by plugin UI slot.</p></div></div><p>Work Order: <strong>{{ $work->work_order_number }}</strong></p></div>'."\n";
        $css="/* {$name} public styles. Load with plugin asset() when needed. */\n";
        $dir=storage_path('app/plugin-builder');File::ensureDirectoryExists($dir,0755,true);
        $path=$dir.'/'.$slug.'-starter-'.now()->format('YmdHis').'.zip';
        $zip=new ZipArchive();if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Could not create starter ZIP.');
        $zip->addFromString('plugin.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        $zip->addFromString('plugin.php',$pluginPhp);
        $zip->addFromString('README.md',$readme);
        $zip->addFromString('resources/views/home.blade.php',$home);
        $zip->addFromString('resources/views/work-card.blade.php',$workCard);
        $zip->addFromString('public/plugin.css',$css);
        $zip->close();
        return $path;
    }

    public function health(Plugin $plugin): array
    {
        $checks=['files'=>is_file(base_path($plugin->path.'/plugin.php')),'compatible'=>true,'dependencies'=>true,'enabled'=>$plugin->enabled,'last_error'=>$plugin->last_error];
        try{$this->assertCompatible($plugin->manifest??[],$plugin);}catch(\Throwable $e){$checks['compatible']=false;$checks['compatibility_error']=$e->getMessage();}
        try{$this->assertDependencies($plugin);}catch(\Throwable $e){$checks['dependencies']=false;$checks['dependency_error']=$e->getMessage();}
        try{$entry=$this->loadEntry($plugin);if($entry instanceof ScratchgardPlugin)$checks['plugin']=$entry->health(new PluginContext($plugin,$this->runtime));}catch(\Throwable $e){$checks['plugin_error']=$e->getMessage();}
        return $checks;
    }

    public function bootEnabled(): void
    {
        if(!file_exists(storage_path('app/installed.lock')))return;
        foreach(Plugin::where('enabled',true)->orderBy('id')->get() as $plugin){
            try{$this->assertCompatible($plugin->manifest??[],$plugin);$this->assertDependencies($plugin);$this->bootOne($plugin);}
            catch(\Throwable $e){Log::error('Plugin boot failed',['plugin'=>$plugin->slug,'error'=>$e->getMessage()]);$plugin->update(['last_error'=>substr($e->getMessage(),0,65000)]);}
        }
    }

    public function bootOne(Plugin $plugin): void
    {
        if($this->runtime->loaded($plugin->slug))return;
        $entry=$this->loadEntry($plugin);$context=new PluginContext($plugin,$this->runtime);
        if($entry instanceof ScratchgardPlugin)$entry->boot($context);
        elseif(is_callable($entry)){$rf=new ReflectionFunction(\Closure::fromCallable($entry));$rf->getNumberOfParameters()>=2?$entry(app(),$plugin):$entry($context);}
        else throw new RuntimeException('plugin.php must return a callable or ScratchgardPlugin implementation.');
        $context->views();$this->runtime->markLoaded($plugin->slug);$plugin->forceFill(['last_error'=>null])->saveQuietly();
    }

    public function runMigrations(Plugin $plugin): void
    {
        $dir=base_path($plugin->path.'/database/migrations');if(!is_dir($dir))return;
        $relative=$plugin->path.'/database/migrations';$code=Artisan::call('migrate',['--path'=>$relative,'--force'=>true]);if($code!==0)throw new RuntimeException('Plugin migrations failed: '.trim(Artisan::output()));
    }

    private function invokeLifecycle(Plugin $plugin,string $method,mixed ...$args): void
    {
        $entry=$this->loadEntry($plugin);$context=new PluginContext($plugin,$this->runtime);
        if($entry instanceof ScratchgardPlugin && method_exists($entry,$method))$entry->{$method}($context,...$args);
    }
    private function loadEntry(Plugin $plugin): mixed
    {
        $file=base_path($plugin->path.'/plugin.php');if(!is_file($file))throw new RuntimeException('Plugin entry file missing: '.$plugin->slug);return require $file;
    }
    private function validateManifest(array $m): void
    {
        foreach(['name','slug','version'] as $key)if(empty($m[$key]))throw new RuntimeException('plugin.json requires '.$key.'.');
        if(!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/',(string)$m['version']))throw new RuntimeException('Plugin version must use semantic version format, e.g. 1.2.3.');
        if(isset($m['settings'])&&!is_array($m['settings']))throw new RuntimeException('Plugin settings schema must be an array.');
        if(isset($m['permissions'])&&!is_array($m['permissions']))throw new RuntimeException('Plugin permissions must be an array.');
    }
    private function safeSlug(string $slug): string
    {
        $safe=preg_replace('/[^a-z0-9_-]/','',strtolower($slug));if(!$safe||$safe!==strtolower($slug))throw new RuntimeException('Plugin slug may contain only lowercase letters, numbers, hyphen and underscore.');return $safe;
    }
    private function assertZipSafe(ZipArchive $zip): void
    {
        for($i=0;$i<$zip->numFiles;$i++){
            $name=$zip->getNameIndex($i)??'';if(str_contains($name,'../')||str_starts_with($name,'/')||str_contains($name,"\0"))throw new RuntimeException('Unsafe ZIP entry.');
            $stat=$zip->statIndex($i);if(($stat['size']??0)>25*1024*1024)throw new RuntimeException('Single plugin file exceeds 25 MB safety limit.');
        }
    }
    private function assertCompatible(array $manifest,?Plugin $self=null): void
    {
        $requires=$manifest['requires']??[];$core=trim((string)@file_get_contents(base_path('VERSION')));
        if(!empty($requires['scratchgard'])&&!$this->matches($core,(string)$requires['scratchgard']))throw new RuntimeException('Plugin requires Scratchgard '.$requires['scratchgard'].'; installed version is '.$core.'.');
        if(!empty($requires['php'])&&!$this->matches(PHP_VERSION,(string)$requires['php']))throw new RuntimeException('Plugin requires PHP '.$requires['php'].'; server has '.PHP_VERSION.'.');
    }
    private function assertDependencies(Plugin $plugin): void
    {
        foreach(($plugin->manifest['requires']['plugins']??[]) as $slug=>$constraint){$dep=Plugin::where('slug',$slug)->first();if(!$dep||!$dep->enabled)throw new RuntimeException('Required plugin is not enabled: '.$slug);if($constraint&&!$this->matches((string)$dep->version,(string)$constraint))throw new RuntimeException($slug.' must satisfy '.$constraint.'; installed '.$dep->version.'.');}
    }
    private function matches(string $version,string $constraint): bool
    {
        $constraint=trim($constraint);if($constraint===''||$constraint==='*')return true;
        foreach(preg_split('/\s*,\s*|\s+/',str_replace('&&',' ',$constraint)) as $part){if($part==='')continue;$op='=';$v=$part;
            if(preg_match('/^(>=|<=|>|<|=|\^|~)(.+)$/',$part,$m)){$op=$m[1];$v=trim($m[2]);}
            if($op==='^'){$base=explode('.',$v);$max=((int)$base[0]+1).'.0.0';if(!(version_compare($version,$v,'>=')&&version_compare($version,$max,'<')))return false;continue;}
            if($op==='~'){$base=explode('.',$v);$max=((int)($base[0]??0)).'.'.((int)($base[1]??0)+1).'.0';if(!(version_compare($version,$v,'>=')&&version_compare($version,$max,'<')))return false;continue;}
            if(!version_compare($version,$v,$op))return false;
        }return true;
    }
    private function registerManifestPermissions(Plugin $plugin): void
    {
        $slugs=[];foreach($plugin->manifest['permissions']??[] as $item){$key=is_string($item)?$item:(string)($item['key']??'');if(!$key)continue;$slug='plugin.'.$plugin->slug.'.'.preg_replace('/[^a-z0-9_.-]/','',strtolower($key));Permission::firstOrCreate(['slug'=>$slug],['name'=>is_array($item)?($item['name']??$key):$key,'group'=>'plugin:'.$plugin->slug]);$slugs[]=$slug;}$plugin->update(['registered_permissions'=>array_values(array_unique($slugs))]);
    }
    private function seedSettingDefaults(Plugin $plugin): void
    {
        foreach($plugin->settings_schema??[] as $field){$key=$field['key']??null;if(!$key||!array_key_exists('default',$field))continue;if(!PluginSetting::where('plugin_id',$plugin->id)->where('key',$key)->exists())PluginSetting::put($plugin,$key,$field['default'],(bool)($field['secret']??false));}
    }
    private function publishAssets(Plugin $plugin): void
    {
        $source=base_path($plugin->path.'/public');if(!is_dir($source))return;$dest=public_path('plugins/'.$plugin->slug);if(File::isDirectory($dest))File::deleteDirectory($dest);File::ensureDirectoryExists($dest,0755,true);File::copyDirectory($source,$dest);
    }
    private function directoryChecksum(string $dir): string
    {
        $parts=[];foreach(File::allFiles($dir) as $file){$rel=str_replace($dir.'/','',$file->getPathname());$parts[]=$rel.':'.hash_file('sha256',$file->getPathname());}sort($parts);return hash('sha256',implode("\n",$parts));
    }
    private function activity(?Plugin $plugin,?int $actorId,string $action,?string $message=null,array $metadata=[]): void
    {
        try{PluginActivityLog::create(['plugin_id'=>$plugin?->id,'actor_id'=>$actorId,'action'=>$action,'message'=>$message,'metadata'=>$metadata?:null,'created_at'=>now()]);}catch(\Throwable $e){Log::warning('Could not record plugin activity',['error'=>$e->getMessage()]);}
    }
}
