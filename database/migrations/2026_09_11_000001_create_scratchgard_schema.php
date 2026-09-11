<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('roles',function(Blueprint $t){$t->id();$t->string('name');$t->string('slug')->unique();$t->boolean('is_internal')->default(true);$t->timestamps();});
        Schema::create('permissions',function(Blueprint $t){$t->id();$t->string('name');$t->string('slug')->unique();$t->string('group')->nullable();$t->timestamps();});
        Schema::create('role_permissions',function(Blueprint $t){$t->foreignId('role_id')->constrained()->cascadeOnDelete();$t->foreignId('permission_id')->constrained()->cascadeOnDelete();$t->primary(['role_id','permission_id']);});

        Schema::create('users',function(Blueprint $t){
            $t->id();$t->foreignId('role_id')->nullable()->constrained()->nullOnDelete();$t->string('name');$t->string('email')->nullable()->unique();$t->string('mobile',30)->nullable()->unique();
            $t->timestamp('email_verified_at')->nullable();$t->timestamp('mobile_verified_at')->nullable();$t->string('password');$t->string('pincode',12)->nullable();
            $t->text('address')->nullable();$t->string('city')->nullable();$t->string('district')->nullable();$t->string('state')->nullable();$t->string('country')->default('India');
            $t->string('status')->default('pending');$t->string('employee_reference')->nullable();$t->string('profile_photo_path')->nullable();$t->rememberToken();$t->timestamps();
            $t->index(['role_id','status']);$t->index('pincode');
        });
        Schema::create('password_reset_tokens',function(Blueprint $t){$t->string('email')->primary();$t->string('token');$t->timestamp('created_at')->nullable();});
        Schema::create('sessions',function(Blueprint $t){$t->string('id')->primary();$t->foreignId('user_id')->nullable()->index();$t->string('ip_address',45)->nullable();$t->text('user_agent')->nullable();$t->longText('payload');$t->integer('last_activity')->index();});

        Schema::create('user_permission_overrides',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('permission_id')->constrained()->cascadeOnDelete();$t->boolean('allowed');$t->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();$t->string('reason')->nullable();$t->timestamps();$t->unique(['user_id','permission_id']);});

        Schema::create('zones',function(Blueprint $t){$t->id();$t->string('name');$t->string('code')->unique();$t->string('state')->nullable();$t->string('status')->default('active');$t->timestamps();});
        Schema::create('pincodes',function(Blueprint $t){$t->id();$t->string('pincode',12)->index();$t->string('locality')->nullable();$t->string('district')->nullable();$t->string('state')->nullable();$t->string('country')->default('India');$t->decimal('latitude',10,7)->nullable();$t->decimal('longitude',10,7)->nullable();$t->timestamps();});
        Schema::create('zone_pincodes',function(Blueprint $t){$t->foreignId('zone_id')->constrained()->cascadeOnDelete();$t->foreignId('pincode_id')->constrained()->cascadeOnDelete();$t->primary(['zone_id','pincode_id']);});
        Schema::create('user_zones',function(Blueprint $t){$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('zone_id')->constrained()->cascadeOnDelete();$t->timestamps();$t->primary(['user_id','zone_id']);});

        Schema::create('vendor_organizations',function(Blueprint $t){$t->id();$t->string('name');$t->string('code')->unique();$t->string('status')->default('active');$t->timestamps();});
        Schema::create('showrooms',function(Blueprint $t){
            $t->id();$t->foreignId('vendor_organization_id')->constrained()->cascadeOnDelete();$t->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');$t->string('code')->unique();$t->text('address');$t->string('pincode',12);$t->string('city')->nullable();$t->string('district')->nullable();$t->string('state')->nullable();$t->string('country')->default('India');
            $t->decimal('latitude',10,7)->nullable();$t->decimal('longitude',10,7)->nullable();$t->unsignedInteger('geofence_radius_m')->default(250);$t->string('status')->default('active');$t->timestamps();$t->index(['pincode','zone_id']);
        });

        Schema::create('vehicles',function(Blueprint $t){$t->id();$t->string('vin',64)->index();$t->string('registration_number',40)->nullable()->index();$t->string('make')->nullable();$t->string('model')->nullable();$t->string('variant')->nullable();$t->string('color')->nullable();$t->unsignedSmallInteger('model_year')->nullable();$t->string('external_reference')->nullable();$t->timestamps();});

        Schema::create('reasons',function(Blueprint $t){$t->id();$t->string('category')->index();$t->string('code')->unique();$t->string('label');$t->json('allowed_roles')->nullable();$t->boolean('comment_required')->default(false);$t->boolean('evidence_required')->default(false);$t->string('target_status')->nullable();$t->string('payment_effect')->nullable();$t->boolean('active')->default(true);$t->integer('sort_order')->default(0);$t->timestamps();});

        Schema::create('work_orders',function(Blueprint $t){
            $t->id();$t->uuid('uuid')->unique();$t->string('work_order_number')->unique();$t->string('external_reference')->nullable()->index();$t->string('creation_source')->default('manual');$t->string('source_system')->nullable()->index();
            $t->foreignId('vendor_organization_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('showroom_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('zonal_manager_id')->nullable()->constrained('users')->nullOnDelete();$t->foreignId('applicator_id')->nullable()->constrained('users')->nullOnDelete();$t->foreignId('external_verifier_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('vehicle_id')->constrained()->restrictOnDelete();$t->string('package_code');$t->string('job_type')->nullable();$t->timestamp('requested_at')->nullable();$t->timestamp('scheduled_at')->nullable();$t->string('status')->default('NEW')->index();
            $t->decimal('pricing_amount',12,2)->nullable();$t->string('pricing_currency',3)->default('INR');$t->json('pricing_snapshot')->nullable();$t->text('notes')->nullable();
            $t->timestamp('submitted_at')->nullable();$t->timestamp('approved_at')->nullable();$t->timestamp('closed_at')->nullable();$t->timestamps();
        });

        Schema::create('assignments',function(Blueprint $t){$t->id();$t->foreignId('work_order_id')->constrained()->cascadeOnDelete();$t->string('assignment_type');$t->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();$t->foreignId('to_user_id')->constrained('users')->restrictOnDelete();$t->foreignId('actor_id')->constrained('users')->restrictOnDelete();$t->foreignId('reason_id')->nullable()->constrained()->nullOnDelete();$t->text('notes')->nullable();$t->timestamp('started_at');$t->timestamp('ended_at')->nullable();$t->json('metadata')->nullable();$t->timestamps();$t->index(['work_order_id','assignment_type','ended_at']);});

        Schema::create('evidence_items',function(Blueprint $t){$t->id();$t->foreignId('work_order_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained()->restrictOnDelete();$t->string('stage')->index();$t->string('vehicle_area')->nullable();$t->string('slot_code');$t->string('disk')->default('local');$t->string('path');$t->string('preview_path')->nullable();$t->string('mime_type')->nullable();$t->unsignedBigInteger('size_bytes')->nullable();$t->string('sha256',64)->nullable()->index();$t->decimal('latitude',10,7)->nullable();$t->decimal('longitude',10,7)->nullable();$t->decimal('gps_accuracy',10,2)->nullable();$t->timestamp('captured_at')->nullable();$t->timestamp('retention_until')->nullable()->index();$t->string('protected_reason')->nullable();$t->timestamp('deleted_at')->nullable();$t->timestamps();$t->index(['work_order_id','stage','slot_code']);});

        Schema::create('checkins',function(Blueprint $t){$t->id();$t->foreignId('work_order_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained()->restrictOnDelete();$t->string('type');$t->decimal('latitude',10,7);$t->decimal('longitude',10,7);$t->decimal('accuracy',10,2)->nullable();$t->decimal('distance_from_showroom_m',12,2)->nullable();$t->boolean('within_geofence')->nullable();$t->foreignId('exception_reason_id')->nullable()->constrained('reasons')->nullOnDelete();$t->text('exception_notes')->nullable();$t->timestamp('checked_at');$t->timestamps();});

        Schema::create('work_events',function(Blueprint $t){$t->id();$t->foreignId('work_order_id')->constrained()->cascadeOnDelete();$t->foreignId('actor_id')->constrained('users')->restrictOnDelete();$t->string('event_type')->index();$t->string('from_status')->nullable();$t->string('to_status')->nullable();$t->foreignId('reason_id')->nullable()->constrained()->nullOnDelete();$t->text('message')->nullable();$t->json('metadata')->nullable();$t->timestamp('created_at')->index();});

        Schema::create('kyc_records',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('document_type')->nullable();$t->string('document_number')->nullable();$t->string('document_name')->nullable();$t->date('document_expiry')->nullable();$t->string('front_path')->nullable();$t->string('back_path')->nullable();$t->string('address_proof_path')->nullable();$t->string('selfie_path')->nullable();$t->string('status')->default('draft')->index();$t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('reviewed_at')->nullable();$t->foreignId('reason_id')->nullable()->constrained()->nullOnDelete();$t->text('notes')->nullable();$t->timestamps();});

        Schema::create('reviews',function(Blueprint $t){$t->id();$t->foreignId('work_order_id')->constrained()->cascadeOnDelete();$t->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();$t->string('reviewer_role');$t->string('decision');$t->foreignId('reason_id')->nullable()->constrained()->nullOnDelete();$t->text('comments')->nullable();$t->timestamp('created_at');$t->index(['work_order_id','decision']);});

        Schema::create('complaints',function(Blueprint $t){$t->id();$t->foreignId('work_order_id')->constrained()->cascadeOnDelete();$t->foreignId('raised_by')->constrained('users')->restrictOnDelete();$t->text('description');$t->json('affected_panels')->nullable();$t->string('severity')->nullable();$t->string('responsibility_status')->nullable();$t->string('status')->default('open')->index();$t->foreignId('assigned_applicator_id')->nullable()->constrained('users')->nullOnDelete();$t->foreignId('reason_id')->nullable()->constrained()->nullOnDelete();$t->timestamp('closed_at')->nullable();$t->timestamps();});

        Schema::create('reworks',function(Blueprint $t){$t->id();$t->foreignId('complaint_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('work_order_id')->constrained()->cascadeOnDelete();$t->string('reference')->unique();$t->foreignId('assigned_applicator_id')->nullable()->constrained('users')->nullOnDelete();$t->string('status')->default('assigned');$t->foreignId('reason_id')->nullable()->constrained()->nullOnDelete();$t->text('corrective_action')->nullable();$t->decimal('film_quantity',10,2)->nullable();$t->string('film_unit',20)->nullable();$t->timestamp('submitted_at')->nullable();$t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('reviewed_at')->nullable();$t->string('review_decision')->nullable();$t->timestamps();});

        Schema::create('payments',function(Blueprint $t){$t->id();$t->foreignId('work_order_id')->unique()->constrained()->cascadeOnDelete();$t->decimal('eligible_amount',12,2)->default(0);$t->string('currency',3)->default('INR');$t->string('status')->default('eligible')->index();$t->decimal('paid_amount',12,2)->nullable();$t->timestamp('paid_at')->nullable();$t->string('mode')->nullable();$t->string('reference')->nullable();$t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamps();});

        Schema::create('settings',function(Blueprint $t){$t->id();$t->string('key')->unique();$t->longText('value')->nullable();$t->string('type')->default('string');$t->string('group')->nullable();$t->timestamps();});
        Schema::create('messages',function(Blueprint $t){$t->id();$t->foreignId('work_order_id')->nullable()->constrained()->cascadeOnDelete();$t->foreignId('sender_id')->constrained('users')->restrictOnDelete();$t->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();$t->text('message');$t->json('metadata')->nullable();$t->timestamp('read_at')->nullable();$t->timestamps();$t->index(['work_order_id','created_at']);});


        Schema::create('evidence_templates',function(Blueprint $t){
            $t->id();$t->string('name');$t->string('package_code')->index();$t->unsignedInteger('version')->default(1);$t->boolean('active')->default(true);$t->timestamps();
        });
        Schema::create('evidence_template_slots',function(Blueprint $t){
            $t->id();$t->foreignId('evidence_template_id')->constrained()->cascadeOnDelete();$t->string('stage');$t->string('slot_code');$t->string('label');
            $t->boolean('mandatory')->default(true);$t->boolean('camera_only')->default(true);$t->boolean('location_required')->default(true);$t->string('closeup_hint')->nullable();$t->unsignedInteger('sort_order')->default(0);$t->timestamps();
            $t->unique(['evidence_template_id','stage','slot_code'],'evidence_template_slot_unique');
        });
        Schema::create('pricing_rules',function(Blueprint $t){
            $t->id();$t->string('package_code')->index();$t->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();$t->string('vehicle_category')->nullable();
            $t->decimal('amount',12,2);$t->string('currency',3)->default('INR');$t->date('effective_from')->nullable();$t->date('effective_to')->nullable();$t->boolean('active')->default(true);$t->timestamps();
        });
        Schema::create('custom_fields',function(Blueprint $t){
            $t->id();$t->string('entity_type')->index();$t->string('field_key');$t->string('label');$t->string('field_type')->default('text');$t->boolean('required')->default(false);
            $t->json('options')->nullable();$t->json('visible_roles')->nullable();$t->json('editable_roles')->nullable();$t->unsignedInteger('sort_order')->default(0);$t->boolean('active')->default(true);$t->timestamps();
            $t->unique(['entity_type','field_key']);
        });
        Schema::create('custom_field_values',function(Blueprint $t){
            $t->id();$t->foreignId('custom_field_id')->constrained()->cascadeOnDelete();$t->string('entity_type')->index();$t->unsignedBigInteger('entity_id')->index();$t->longText('value')->nullable();$t->timestamps();
            $t->unique(['custom_field_id','entity_type','entity_id'],'custom_field_value_unique');
        });

        Schema::create('api_tokens',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('name');$t->string('token_hash',64)->unique();$t->json('abilities')->nullable();$t->timestamp('last_used_at')->nullable();$t->timestamp('expires_at')->nullable()->index();$t->timestamps();});

        Schema::create('jobs',function(Blueprint $t){$t->id();$t->string('queue')->index();$t->longText('payload');$t->unsignedTinyInteger('attempts');$t->unsignedInteger('reserved_at')->nullable();$t->unsignedInteger('available_at');$t->unsignedInteger('created_at');});
        Schema::create('job_batches',function(Blueprint $t){$t->string('id')->primary();$t->string('name');$t->integer('total_jobs');$t->integer('pending_jobs');$t->integer('failed_jobs');$t->longText('failed_job_ids');$t->mediumText('options')->nullable();$t->integer('cancelled_at')->nullable();$t->integer('created_at');$t->integer('finished_at')->nullable();});
        Schema::create('failed_jobs',function(Blueprint $t){$t->id();$t->string('uuid')->unique();$t->text('connection');$t->text('queue');$t->longText('payload');$t->longText('exception');$t->timestamp('failed_at')->useCurrent();});

        Schema::create('cache',function(Blueprint $t){$t->string('key')->primary();$t->mediumText('value');$t->integer('expiration');});
        Schema::create('cache_locks',function(Blueprint $t){$t->string('key')->primary();$t->string('owner');$t->integer('expiration');});
    }

    public function down(): void {
        foreach(['cache_locks','cache','failed_jobs','job_batches','jobs','custom_field_values','custom_fields','pricing_rules','evidence_template_slots','evidence_templates','api_tokens','messages','settings','payments','reworks','complaints','reviews','kyc_records','work_events','checkins','evidence_items','assignments','work_orders','reasons','vehicles','showrooms','vendor_organizations','user_zones','zone_pincodes','pincodes','zones','user_permission_overrides','sessions','password_reset_tokens','users','role_permissions','permissions','roles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
