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
            $t->foreignId('vendor_organization_id')->nullable()->constrained('vendor_organizations')->nullOnDelete();
            $t->foreignId('showroom_id')->nullable()->constrained('showrooms')->nullOnDelete();
            $t->string('designation',120)->nullable();
            $t->boolean('approval_otp_required')->default(true);
        });

        Schema::table('work_orders', function (Blueprint $t) {
            $t->string('tracking_token',64)->nullable()->unique();
            $t->boolean('tracking_enabled')->default(true);
        });

        foreach (DB::table('work_orders')->whereNull('tracking_token')->select('id')->orderBy('id')->cursor() as $row) {
            DB::table('work_orders')->where('id',$row->id)->update(['tracking_token'=>hash('sha256',Str::uuid()->toString().Str::random(48).$row->id)]);
        }

        Schema::table('reviews', function (Blueprint $t) {
            $t->string('verification_method',40)->nullable();
            $t->foreignId('identity_verification_id')->nullable()->constrained('identity_verifications')->nullOnDelete();
        });

        Schema::create('notification_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $t->string('event_type',80)->index();
            $t->string('email',190)->nullable()->index();
            $t->string('subject');
            $t->string('status',30)->default('pending')->index();
            $t->text('error')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });

        foreach ([
            ['key'=>'email_notifications_enabled','value'=>'1','type'=>'bool','group'=>'email'],
            ['key'=>'email_notify_super_admins','value'=>'1','type'=>'bool','group'=>'email'],
            ['key'=>'email_notify_work_delegators','value'=>'1','type'=>'bool','group'=>'email'],
        ] as $setting) {
            DB::table('settings')->updateOrInsert(['key'=>$setting['key']],array_merge($setting,['created_at'=>now(),'updated_at'=>now()]));
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::table('reviews', function (Blueprint $t) {
            $t->dropConstrainedForeignId('identity_verification_id');
            $t->dropColumn('verification_method');
        });
        Schema::table('work_orders', function (Blueprint $t) {
            $t->dropUnique(['tracking_token']);
            $t->dropColumn(['tracking_token','tracking_enabled']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('vendor_organization_id');
            $t->dropConstrainedForeignId('showroom_id');
            $t->dropColumn(['designation','approval_otp_required']);
        });
    }
};
