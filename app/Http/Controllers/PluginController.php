<?php
namespace App\Http\Controllers;

use App\Models\Plugin;
use App\Models\PluginSetting;
use App\Services\PluginManager;
use Illuminate\Http\Request;

class PluginController extends Controller
{
    private function ensureSuperAdmin(): void {abort_unless(auth()->user()?->role?->slug==='super_admin',403,'Plugin management is Super Admin-only.');}

    public function builder()
    {
        $this->ensureSuperAdmin();
        return view('plugins.builder',['catalog'=>config('plugin_ui',[])]);
    }
    public function downloadStarter(Request $request,PluginManager $manager)
    {
        $this->ensureSuperAdmin();
        $data=$request->validate([
            'name'=>'required|string|max:120','slug'=>'required|string|max:80|regex:/^[a-z0-9][a-z0-9-]*$/',
            'description'=>'nullable|string|max:500','author'=>'nullable|string|max:120',
        ]);
        try{
            $path=$manager->buildStarterPackage($data);
            return response()->download($path,basename($path))->deleteFileAfterSend(true);
        }catch(\Throwable $e){return back()->withErrors(['builder'=>$e->getMessage()]);}
    }
    public function index(PluginManager $manager)
    {
        $this->ensureSuperAdmin();
        $plugins=Plugin::with(['activity'=>fn($q)=>$q->limit(8)])->orderBy('name')->get();
        $health=$plugins->mapWithKeys(fn($p)=>[$p->id=>$manager->health($p)]);
        return view('plugins.index',compact('plugins','health'));
    }
    public function show(Plugin $plugin, PluginManager $manager)
    {
        $this->ensureSuperAdmin();$plugin->load(['settings','activity.actor']);$health=$manager->health($plugin);
        return view('plugins.show',compact('plugin','health'));
    }
    public function install(Request $request,PluginManager $manager)
    {
        $this->ensureSuperAdmin();$request->validate(['plugin'=>'required|file|max:30720']);
        try{$p=$manager->install($request->file('plugin')->getRealPath(),auth()->id());return redirect()->route('plugins.show',$p)->with('status','Plugin package installed/updated. New installs remain disabled until Super Admin enables them.');}
        catch(\Throwable $e){return back()->withErrors(['plugin'=>$e->getMessage()]);}
    }
    public function enable(Plugin $plugin,PluginManager $manager)
    {
        $this->ensureSuperAdmin();try{$manager->enable($plugin,auth()->id());return back()->with('status',$plugin->name.' enabled.');}catch(\Throwable $e){return back()->withErrors(['plugin'=>$e->getMessage()]);}
    }
    public function disable(Plugin $plugin,PluginManager $manager)
    {
        $this->ensureSuperAdmin();try{$manager->disable($plugin,auth()->id());return back()->with('status',$plugin->name.' disabled.');}catch(\Throwable $e){return back()->withErrors(['plugin'=>$e->getMessage()]);}
    }
    public function settings(Plugin $plugin)
    {
        $this->ensureSuperAdmin();$values=[];foreach($plugin->settings as $row)$values[$row->key]=$row->decodedValue();return view('plugins.settings',compact('plugin','values'));
    }
    public function saveSettings(Request $request,Plugin $plugin,PluginManager $manager)
    {
        $this->ensureSuperAdmin();$manager->saveSettings($plugin,$request->input('settings',[]),auth()->id());return back()->with('status','Plugin settings saved.');
    }
    public function migrate(Plugin $plugin,PluginManager $manager)
    {
        $this->ensureSuperAdmin();try{$manager->runMigrations($plugin);return back()->with('status','Plugin migrations completed.');}catch(\Throwable $e){return back()->withErrors(['plugin'=>$e->getMessage()]);}
    }
    public function destroy(Request $request,Plugin $plugin,PluginManager $manager)
    {
        $this->ensureSuperAdmin();try{$manager->uninstall($plugin,auth()->id(),$request->boolean('purge_data'));return redirect()->route('plugins.index')->with('status','Plugin removed. '.($request->boolean('purge_data')?'Plugin cleanup callback was allowed to purge its own data.':'Plugin-owned data was preserved where possible.'));}catch(\Throwable $e){return back()->withErrors(['plugin'=>$e->getMessage()]);}
    }
}
