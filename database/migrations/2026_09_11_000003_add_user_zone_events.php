<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_zone_events',function(Blueprint $t){
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('previous_zone_ids')->nullable();
            $t->json('new_zone_ids');
            $t->foreignId('previous_primary_zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $t->foreignId('new_primary_zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $t->string('source',40)->default('admin');
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->index();
        });
    }
    public function down(): void { Schema::dropIfExists('user_zone_events'); }
};
