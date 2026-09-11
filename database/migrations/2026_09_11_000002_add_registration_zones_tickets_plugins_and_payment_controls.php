<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('primary_zone_id')->nullable()->after('role_id')->constrained('zones')->nullOnDelete();
            $t->boolean('allow_multiple_zones')->default(false)->after('status');
            $t->boolean('allow_zone_self_selection')->nullable()->after('allow_multiple_zones');
            $t->string('pending_email')->nullable()->after('email');
            $t->string('pending_mobile',30)->nullable()->after('mobile');
            $t->timestamp('profile_verified_at')->nullable();
        });

        Schema::table('user_zones', function (Blueprint $t) {
            $t->boolean('is_primary')->default(false);
            $t->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('source',40)->default('admin');
        });

        Schema::table('pincodes', function (Blueprint $t) {
            $t->string('office_name')->nullable();
            $t->string('division_name')->nullable();
            $t->string('region_name')->nullable();
            $t->string('circle_name')->nullable();
            $t->string('delivery_status')->nullable();
            $t->string('source_version')->nullable();
            $t->index(['state','district']);
            $t->index(['district','locality']);
        });

        Schema::create('zone_coverage_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $t->string('scope_type',20)->index(); // state, district, city, pincode
            $t->string('state')->nullable()->index();
            $t->string('district')->nullable()->index();
            $t->string('city')->nullable()->index();
            $t->string('pincode',12)->nullable()->index();
            $t->unsignedInteger('priority')->default(100);
            $t->boolean('active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['zone_id','scope_type','state','district','city','pincode'],'zone_scope_unique');
        });

        Schema::create('identity_verifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('channel',20)->index(); // email/mobile
            $t->string('destination')->index();
            $t->string('purpose',40)->default('registration')->index();
            $t->string('code_hash',64);
            $t->timestamp('expires_at')->index();
            $t->timestamp('verified_at')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->json('metadata')->nullable();
            $t->timestamps();
        });

        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->string('ticket_number')->unique();
            $t->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('raised_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->string('category',50)->default('general')->index();
            $t->string('subject');
            $t->text('description')->nullable();
            $t->string('priority',20)->default('normal')->index();
            $t->string('status',30)->default('open')->index();
            $t->string('source',30)->default('web');
            $t->timestamp('last_activity_at')->nullable()->index();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('ticket_participants', function (Blueprint $t) {
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('participant_role',40)->nullable();
            $t->timestamps();
            $t->primary(['ticket_id','user_id']);
        });

        Schema::create('ticket_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $t->text('message');
            $t->json('attachments')->nullable();
            $t->boolean('internal_only')->default(false);
            $t->timestamps();
        });

        Schema::create('ticket_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('event_type')->index();
            $t->string('from_status')->nullable();
            $t->string('to_status')->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->index();
        });

        Schema::table('complaints', function (Blueprint $t) {
            $t->foreignId('ticket_id')->nullable()->after('id')->constrained('tickets')->nullOnDelete();
        });

        Schema::table('work_orders', function (Blueprint $t) {
            $t->foreignId('parent_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $t->foreignId('origin_rework_id')->nullable();
            $t->string('relationship_type',40)->nullable();
        });

        Schema::table('reworks', function (Blueprint $t) {
            $t->string('scope_classification',40)->default('in_scope_rework')->index();
            $t->string('payment_treatment',40)->default('no_new_payment');
            $t->foreignId('scope_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('scope_changed_at')->nullable();
            $t->text('scope_notes')->nullable();
            $t->foreignId('linked_new_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->string('previous_status',30)->nullable();
            $t->timestamp('held_at')->nullable();
            $t->foreignId('hold_reason_id')->nullable()->constrained('reasons')->nullOnDelete();
            $t->timestamp('reverted_at')->nullable();
            $t->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('revert_reason_id')->nullable()->constrained('reasons')->nullOnDelete();
            $t->text('revert_notes')->nullable();
        });

        Schema::create('plugins', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->string('version')->nullable();
            $t->string('path');
            $t->boolean('enabled')->default(false)->index();
            $t->json('manifest')->nullable();
            $t->string('checksum',64)->nullable();
            $t->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('installed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('api_clients', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('client_id')->unique();
            $t->string('secret_hash');
            $t->json('abilities')->nullable();
            $t->json('allowed_ips')->nullable();
            $t->boolean('active')->default(true)->index();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
        Schema::dropIfExists('plugins');
        Schema::table('payments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('hold_reason_id');
            $t->dropConstrainedForeignId('reverted_by');
            $t->dropConstrainedForeignId('revert_reason_id');
            $t->dropColumn(['previous_status','held_at','reverted_at','revert_notes']);
        });
        Schema::table('reworks', function (Blueprint $t) {
            $t->dropConstrainedForeignId('scope_changed_by');
            $t->dropConstrainedForeignId('linked_new_work_order_id');
            $t->dropColumn(['scope_classification','payment_treatment','scope_changed_at','scope_notes']);
        });
        Schema::table('work_orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('parent_work_order_id');
            $t->dropColumn(['origin_rework_id','relationship_type']);
        });
        Schema::table('complaints', function (Blueprint $t) { $t->dropConstrainedForeignId('ticket_id'); });
        Schema::dropIfExists('ticket_events');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('ticket_participants');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('identity_verifications');
        Schema::dropIfExists('zone_coverage_rules');
        Schema::table('pincodes', function (Blueprint $t) {
            $t->dropColumn(['office_name','division_name','region_name','circle_name','delivery_status','source_version']);
        });
        Schema::table('user_zones', function (Blueprint $t) {
            $t->dropConstrainedForeignId('assigned_by');
            $t->dropColumn(['is_primary','source']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('primary_zone_id');
            $t->dropColumn(['allow_multiple_zones','allow_zone_self_selection','pending_email','pending_mobile','profile_verified_at']);
        });
    }
};
