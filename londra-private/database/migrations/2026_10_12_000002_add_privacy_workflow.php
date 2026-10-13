<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $t): void {
            $t->timestamp('last_substantive_contact_at')->nullable()->index();
            $t->timestamp('converted_at')->nullable()->index();
            $t->timestamp('legal_hold_at')->nullable();
            $t->text('legal_hold_reason')->nullable();
            $t->timestamp('active_until')->nullable(); $t->text('active_reason')->nullable();
        });
        Schema::create('data_requests', function (Blueprint $t): void {
            $t->id(); $t->string('reference', 40)->unique(); $t->string('submission_key', 64)->unique();
            $t->string('payload_fingerprint', 64); $t->text('email')->nullable(); $t->string('email_lookup', 64)->nullable()->index();
            $t->string('locale', 2); $t->string('status', 30)->default('received')->index();
            $t->unsignedBigInteger('inquiry_cutoff_id')->default(0);
            $t->text('verification_token')->nullable(); $t->string('verification_hash', 64)->nullable()->unique();
            $t->timestamp('verification_expires_at')->nullable(); $t->timestamp('verified_at')->nullable();
            $t->timestamp('due_at')->index(); $t->timestamp('database_erased_at')->nullable();
            $t->timestamp('completed_at')->nullable(); $t->text('decision_reason')->nullable();
            $t->unsignedInteger('erased_count')->default(0); $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('privacy_deliveries', function (Blueprint $t): void {
            $t->id(); $t->foreignId('data_request_id')->constrained()->cascadeOnDelete(); $t->string('kind', 20);
            $t->string('status', 20)->default('pending')->index(); $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestamp('queued_at')->nullable(); $t->timestamp('smtp_accepted_at')->nullable(); $t->string('last_error_class')->nullable();
            $t->timestamps(); $t->unique(['data_request_id', 'kind']);
        });
        Schema::create('erasure_events', function (Blueprint $t): void {
            $t->id(); $t->string('reason', 30); $t->unsignedInteger('record_count');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $t->timestamp('created_at');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('erasure_events'); Schema::dropIfExists('privacy_deliveries'); Schema::dropIfExists('data_requests');
        Schema::table('inquiries', function (Blueprint $t): void { $t->dropColumn(['last_substantive_contact_at', 'converted_at', 'legal_hold_at', 'legal_hold_reason', 'active_until', 'active_reason']); });
    }
};
