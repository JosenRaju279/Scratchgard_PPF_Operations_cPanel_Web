<?php
namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\IdentityNormalizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InstallerController extends Controller
{
    public function index()
    {
        if (file_exists(storage_path('app/installed.lock'))) {
            return redirect()->route('login');
        }

        $checks = [
            'PHP >= 8.3' => version_compare(PHP_VERSION,'8.3.0','>='),
            'PDO' => extension_loaded('pdo'),
            'PDO MySQL or PostgreSQL' => extension_loaded('pdo_mysql') || extension_loaded('pdo_pgsql'),
            'cURL' => extension_loaded('curl'),
            'mbstring' => extension_loaded('mbstring'),
            'OpenSSL' => extension_loaded('openssl'),
            'Fileinfo' => extension_loaded('fileinfo'),
            'Zip (required for plugin ZIP uploads)' => extension_loaded('zip'),
            'storage writable' => is_writable(storage_path()),
            'bootstrap/cache writable' => is_writable(base_path('bootstrap/cache')),
        ];

        return view('install.index', compact('checks'));
    }

    public function install(Request $request, IdentityNormalizerService $identity)
    {
        abort_if(file_exists(storage_path('app/installed.lock')),403,'Application is already installed.');

        $validated = $request->validate([
            'app_name'=>'required|string|max:120',
            'app_url'=>'required|url',
            'db_connection'=>'required|in:mysql,pgsql',
            'db_host'=>'required|string',
            'db_port'=>'required|integer',
            'db_database'=>'required|string',
            'db_username'=>'required|string',
            'db_password'=>'nullable|string',
            'evidence_disk'=>'required|in:local,s3',
            'aws_access_key_id'=>'nullable|string',
            'aws_secret_access_key'=>'nullable|string',
            'aws_default_region'=>'nullable|string',
            'aws_bucket'=>'nullable|string',
            'admin_name'=>'required|string|max:120',
            'admin_email'=>'required|email|max:190',
            'admin_mobile'=>'nullable|string|max:30',
            'admin_password'=>'required|string|min:10|confirmed',
        ]);

        $db = [
            'driver'=>$validated['db_connection'],
            'host'=>$validated['db_host'],
            'port'=>$validated['db_port'],
            'database'=>$validated['db_database'],
            'username'=>$validated['db_username'],
            'password'=>$validated['db_password'] ?? '',
        ];

        try {
            config([
                'database.default'=>$db['driver'],
                "database.connections.{$db['driver']}.host"=>$db['host'],
                "database.connections.{$db['driver']}.port"=>$db['port'],
                "database.connections.{$db['driver']}.database"=>$db['database'],
                "database.connections.{$db['driver']}.username"=>$db['username'],
                "database.connections.{$db['driver']}.password"=>$db['password'],
            ]);
            DB::purge($db['driver']);
            DB::connection($db['driver'])->getPdo();
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['database'=>'Database connection failed: '.$e->getMessage()]);
        }

        $appKey = 'base64:'.base64_encode(random_bytes(32));
        $env = $this->buildEnv($validated,$appKey);
        file_put_contents(base_path('.env'),$env);

        config([
            'app.key'=>$appKey,
            'app.name'=>$validated['app_name'],
            'app.url'=>$validated['app_url'],
            'filesystems.default'=>$validated['evidence_disk'],
        ]);

        if ($validated['evidence_disk'] === 's3') {
            config([
                'filesystems.disks.s3.key'=>$validated['aws_access_key_id'] ?? '',
                'filesystems.disks.s3.secret'=>$validated['aws_secret_access_key'] ?? '',
                'filesystems.disks.s3.region'=>$validated['aws_default_region'] ?? 'ap-south-1',
                'filesystems.disks.s3.bucket'=>$validated['aws_bucket'] ?? '',
            ]);
            try {
                Storage::disk('s3')->put('scratchgard-install-check.txt','Scratchgard storage check '.now()->toIso8601String());
                Storage::disk('s3')->delete('scratchgard-install-check.txt');
            } catch (Throwable $e) {
                return back()->withInput()->withErrors(['s3'=>'S3 connection failed: '.$e->getMessage()]);
            }
        }

        try {
            Artisan::call('config:clear');
            Artisan::call('migrate',['--force'=>true]);
            Artisan::call('db:seed',['--force'=>true]);

            $role = Role::where('slug','super_admin')->firstOrFail();
            $adminEmail=$identity->normalizeEmail($validated['admin_email']);
            $mobileParts=!empty($validated['admin_mobile'])?$identity->normalizeMobile($validated['admin_mobile'],null,(string)\App\Models\Setting::getValue('default_mobile_country_code','+91')):['country_code'=>null,'national_number'=>null,'canonical'=>null];
            User::updateOrCreate(
                ['email'=>$adminEmail],
                [
                    'role_id'=>$role->id,
                    'name'=>$validated['admin_name'],
                    'username'=>$identity->generateUsername($validated['admin_name']),
                    'mobile'=>$mobileParts['canonical'],
                    'mobile_country_code'=>$mobileParts['country_code'],
                    'mobile_national_number'=>$mobileParts['national_number'],
                    'password'=>Hash::make($validated['admin_password']),
                    'status'=>'active',
                    'email_verified_at'=>now(),
                    'mobile_verified_at'=>!empty($validated['admin_mobile']) ? now() : null,
                    'profile_verified_at'=>now(),
                    'country'=>'India',
                ]
            );

            file_put_contents(storage_path('app/installed.lock'),json_encode([
                'installed_at'=>now()->toIso8601String(),
                'version'=>'2.8.0',
            ],JSON_PRETTY_PRINT));

            try { Artisan::call('storage:link'); } catch (\Throwable $ignored) {}
            Artisan::call('optimize:clear');
        } catch (Throwable $e) {
            @unlink(storage_path('app/installed.lock'));
            return back()->withInput()->withErrors(['install'=>'Installation failed: '.$e->getMessage()]);
        }

        return redirect()->route('login')->with('status','Installation completed. Sign in with the Super Admin account.');
    }

    private function envValue(?string $value): string
    {
        $value=(string)$value;
        if ($value==='' ) return '""';
        return '"'.str_replace(['\\','"',"\r","\n"],['\\\\','\\"','','\\n'],$value).'"';
    }

    private function buildEnv(array $v,string $key): string
    {
        $lines = [
            'APP_NAME='.$this->envValue($v['app_name']),
            'APP_ENV=production',
            'APP_KEY='.$this->envValue($key),
            'APP_DEBUG=false',
            'APP_URL='.$this->envValue(rtrim($v['app_url'],'/')),
            'APP_TIMEZONE=Asia/Kolkata',
            '',
            'LOG_CHANNEL=stack',
            'LOG_LEVEL=warning',
            '',
            'DB_CONNECTION='.$v['db_connection'],
            'DB_HOST='.$this->envValue($v['db_host']),
            'DB_PORT='.$v['db_port'],
            'DB_DATABASE='.$this->envValue($v['db_database']),
            'DB_USERNAME='.$this->envValue($v['db_username']),
            'DB_PASSWORD='.$this->envValue($v['db_password'] ?? ''),
            '',
            'SESSION_DRIVER=file',
            'CACHE_STORE=file',
            'QUEUE_CONNECTION=database',
            '',
            'FILESYSTEM_DISK='.$v['evidence_disk'],
            'EVIDENCE_DISK='.$v['evidence_disk'],
            '',
            'AWS_ACCESS_KEY_ID='.$this->envValue($v['aws_access_key_id'] ?? ''),
            'AWS_SECRET_ACCESS_KEY='.$this->envValue($v['aws_secret_access_key'] ?? ''),
            'AWS_DEFAULT_REGION='.$this->envValue($v['aws_default_region'] ?? 'ap-south-1'),
            'AWS_BUCKET='.$this->envValue($v['aws_bucket'] ?? ''),
            'AWS_URL=""',
            'AWS_ENDPOINT=""',
            'AWS_USE_PATH_STYLE_ENDPOINT=false',
            '',
            'MAIL_MAILER=smtp',
            'MAIL_HOST=""',
            'MAIL_PORT=587',
            'MAIL_USERNAME=""',
            'MAIL_PASSWORD=""',
            'MAIL_FROM_ADDRESS="no-reply@example.com"',
            'MAIL_FROM_NAME=${APP_NAME}',
            '',
            'INSTALLER_ENABLED=false',
        ];
        return implode(PHP_EOL,$lines).PHP_EOL;
    }
}
