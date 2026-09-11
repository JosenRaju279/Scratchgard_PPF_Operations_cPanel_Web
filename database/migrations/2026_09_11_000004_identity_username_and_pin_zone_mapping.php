<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('username', 40)->nullable()->unique()->after('name');
            $t->string('mobile_country_code', 8)->nullable()->after('mobile');
            $t->string('mobile_national_number', 10)->nullable()->after('mobile_country_code');
            $t->index(['mobile_country_code','mobile_national_number']);
        });

        Schema::create('zone_mapping_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $t->string('source_scope',20)->default('pincode'); // state/district/city/pincode selection source only
            $t->string('source_state')->nullable();
            $t->string('source_district')->nullable();
            $t->string('source_city')->nullable();
            $t->string('source_pincode',12)->nullable();
            $t->unsignedInteger('pincode_count')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('zone_pin_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $t->string('pincode',6);
            $t->timestamps();
            $t->unique(['zone_id','pincode']);
            $t->index('pincode');
        });

        Schema::create('zone_pin_assignment_sources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->constrained('zone_pin_assignments')->cascadeOnDelete();
            $t->foreignId('batch_id')->constrained('zone_mapping_batches')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['assignment_id','batch_id']);
        });


        // Normalize existing email/mobile values before new logins rely on canonical identity.
        $rows = DB::table('users')->select('id','email','mobile')->orderBy('id')->get();
        $seenEmails=[]; $seenMobiles=[];
        foreach($rows as $u){
            $email=$u->email!==null ? strtolower(trim((string)$u->email)) : null;
            $email=$email===''?null:$email;
            if($email){
                if(isset($seenEmails[$email]) && $seenEmails[$email]!==$u->id) throw new RuntimeException('Email normalization collision between user IDs '.$seenEmails[$email].' and '.$u->id.' for '.$email.'. Resolve duplicate accounts before upgrading.');
                $seenEmails[$email]=$u->id;
            }
            $canonical=null; $cc=null; $national=null;
            if($u->mobile){
                $digits=preg_replace('/\D+/', '', (string)$u->mobile);
                if(strlen($digits)===10){$cc='91';$national=$digits;}
                elseif(strlen($digits)>10 && strlen($digits)<=14){$national=substr($digits,-10);$cc=substr($digits,0,-10);}
                if($cc && $national && strlen($national)===10){$canonical='+'.$cc.$national;}
                if($canonical){
                    if(isset($seenMobiles[$canonical]) && $seenMobiles[$canonical]!==$u->id) throw new RuntimeException('Mobile normalization collision between user IDs '.$seenMobiles[$canonical].' and '.$u->id.' for '.$canonical.'. Resolve duplicate accounts before upgrading.');
                    $seenMobiles[$canonical]=$u->id;
                }
            }
            DB::table('users')->where('id',$u->id)->update([
                'email'=>$email,
                'mobile'=>$canonical ?: $u->mobile,
                'mobile_country_code'=>$cc?'+'.$cc:null,
                'mobile_national_number'=>$national,
            ]);
        }

        // Backfill deterministic unique usernames for existing users.
        $used = [];
        foreach (DB::table('users')->select('id','name','email')->orderBy('id')->get() as $u) {
            $base = Str::lower(Str::ascii((string)$u->name));
            $base = preg_replace('/\s+/', '.', $base);
            $base = preg_replace('/[^a-z0-9._]/', '', $base);
            $base = trim((string)$base, '._');
            if (strlen($base) < 4) $base = 'user'.($u->id);
            $base = substr($base, 0, 24);
            $candidate = $base;
            $n = 0;
            while (isset($used[$candidate]) || DB::table('users')->where('username',$candidate)->exists()) {
                $n++;
                $candidate = substr($base,0,24).'.'.$u->id.($n > 1 ? '.'.$n : '');
            }
            $used[$candidate] = true;
            DB::table('users')->where('id',$u->id)->update(['username'=>$candidate]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_pin_assignment_sources');
        Schema::dropIfExists('zone_pin_assignments');
        Schema::dropIfExists('zone_mapping_batches');
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['mobile_country_code','mobile_national_number']);
            $t->dropColumn(['username','mobile_country_code','mobile_national_number']);
        });
    }
};
