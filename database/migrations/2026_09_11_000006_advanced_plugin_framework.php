<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plugins', function (Blueprint $t) {
            $t->text('description')->nullable()->after('name');
            $t->string('author')->nullable()->after('description');
            $t->string('homepage')->nullable()->after('author');
            $t->string('previous_version')->nullable()->after('version');
            $t->json('settings_schema')->nullable()->after('manifest');
            $t->json('registered_permissions')->nullable()->after('settings_schema');
            $t->text('last_error')->nullable()->after('checksum');
            $t->timestamp('activated_at')->nullable()->after('installed_at');
            $t->timestamp('disabled_at')->nullable()->after('activated_at');
        });

        Schema::create('plugin_settings', function (Blueprint $t) {
            $t->id();$t->foreignId('plugin_id')->constrained('plugins')->cascadeOnDelete();$t->string('key');$t->longText('value')->nullable();$t->boolean('encrypted')->default(false);$t->timestamps();$t->unique(['plugin_id','key']);
        });
        Schema::create('plugin_activity_logs', function (Blueprint $t) {
            $t->id();$t->foreignId('plugin_id')->nullable()->constrained('plugins')->nullOnDelete();$t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();$t->string('action',80)->index();$t->text('message')->nullable();$t->json('metadata')->nullable();$t->timestamp('created_at')->useCurrent();
        });

        // Plugin manager is intentionally not a grantable capability. Super Admin role is enforced in middleware/controllers.
        DB::table('permissions')->where('slug','plugins.manage')->delete();
    }
    public function down(): void
    {
        Schema::dropIfExists('plugin_activity_logs');Schema::dropIfExists('plugin_settings');
        Schema::table('plugins', function (Blueprint $t) {
            $t->dropColumn(['description','author','homepage','previous_version','settings_schema','registered_permissions','last_error','activated_at','disabled_at']);
        });
    }
};
