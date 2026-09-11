<?php
namespace App\Providers;

use App\Models\Setting;
use App\Plugins\PluginRuntime;
use App\Services\PluginManager;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginRuntime::class,fn()=>new PluginRuntime());
        $this->app->singleton(PluginManager::class,fn($app)=>new PluginManager($app->make(PluginRuntime::class)));
    }
    public function boot(): void
    {
        if(!file_exists(storage_path('app/installed.lock')))return;
        try{
            $host=Setting::getValue('smtp_host');if($host)config(['mail.default'=>'smtp','mail.mailers.smtp.host'=>$host,'mail.mailers.smtp.port'=>(int)Setting::getValue('smtp_port',587),'mail.mailers.smtp.username'=>Setting::getValue('smtp_username'),'mail.mailers.smtp.password'=>Setting::getValue('smtp_password'),'mail.from.address'=>Setting::getValue('smtp_from_address',env('MAIL_FROM_ADDRESS')),'mail.from.name'=>Setting::getValue('smtp_from_name',config('app.name'))]);
            $logo=Setting::getValue('brand_logo_path');$favicon=Setting::getValue('brand_favicon_path');
            View::share('brandLogoUrl',$logo?Storage::disk('public')->url($logo):null);View::share('brandFaviconUrl',$favicon?Storage::disk('public')->url($favicon):null);View::share('brandName',Setting::getValue('app_brand_name','Scratchgard'));
            View::share('brandTheme',['theme'=>Setting::getValue('theme_color','#0B1727'),'accent'=>Setting::getValue('accent_color','#27B7E8'),'text'=>Setting::getValue('text_color','#EDF7FF'),'font'=>Setting::getValue('font_family','Inter, ui-sans-serif, system-ui, sans-serif'),'base'=>(int)Setting::getValue('base_font_size',16),'heading'=>(float)Setting::getValue('heading_scale',1.22),'footer'=>Setting::getValue('footer_text','Scratchgard PPF Operations')]);
            app(PluginManager::class)->bootEnabled();
            View::composer('*',function($view){$user=auth()->user();$view->with('pluginNavigation',$user?app(PluginRuntime::class)->navigationFor($user):[]);});
        }catch(\Throwable $e){report($e);}
    }
}
