<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t): void {
            $t->id(); $t->unsignedTinyInteger('administrator_slot')->default(1)->unique();
            $t->string('name'); $t->string('email')->unique(); $t->timestamp('email_verified_at')->nullable();
            $t->string('password'); $t->text('app_authentication_secret')->nullable();
            $t->text('app_authentication_recovery_codes')->nullable(); $t->rememberToken(); $t->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $t): void { $t->string('email')->primary(); $t->string('token'); $t->timestamp('created_at')->nullable(); });
        Schema::create('sessions', function (Blueprint $t): void {
            $t->string('id')->primary(); $t->foreignId('user_id')->nullable()->index(); $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable(); $t->longText('payload'); $t->integer('last_activity')->index();
        });
        Schema::create('cache', function (Blueprint $t): void { $t->string('key')->primary(); $t->mediumText('value'); $t->integer('expiration')->index(); });
        Schema::create('cache_locks', function (Blueprint $t): void { $t->string('key')->primary(); $t->string('owner'); $t->integer('expiration'); });
        Schema::create('jobs', function (Blueprint $t): void { $t->bigIncrements('id'); $t->string('queue')->index(); $t->longText('payload'); $t->unsignedTinyInteger('attempts'); $t->unsignedInteger('reserved_at')->nullable(); $t->unsignedInteger('available_at'); $t->unsignedInteger('created_at'); });
        Schema::create('failed_jobs', function (Blueprint $t): void { $t->id(); $t->string('uuid')->unique(); $t->text('connection'); $t->text('queue'); $t->longText('payload'); $t->longText('exception'); $t->timestamp('failed_at')->useCurrent(); });
        Schema::create('pages', function (Blueprint $t): void {
            $t->id(); $t->string('key', 80)->unique(); $t->string('kind', 30)->index(); $t->string('image_key', 80)->nullable();
            $t->boolean('published')->default(false)->index(); $t->boolean('approval_required')->default(false);
            $t->timestamp('approved_at')->nullable(); $t->unsignedSmallInteger('sort_order')->default(0);
            $t->string('source_reference')->nullable(); $t->timestamps();
        });
        Schema::create('page_translations', function (Blueprint $t): void {
            $t->id(); $t->foreignId('page_id')->constrained()->cascadeOnDelete(); $t->string('locale', 2);
            $t->string('slug', 190); $t->string('title', 200); $t->string('eyebrow', 160)->nullable();
            $t->text('intro'); $t->longText('body')->nullable(); $t->json('highlights')->nullable(); $t->json('faq_items')->nullable();
            $t->string('seo_title', 200); $t->text('meta_description'); $t->string('image_alt', 255)->nullable();
            $t->boolean('ready')->default(false); $t->timestamps();
            $t->unique(['page_id', 'locale']); $t->unique(['locale', 'slug']);
        });
        Schema::create('content_revisions', function (Blueprint $t): void {
            $t->id(); $t->foreignId('page_id')->constrained()->cascadeOnDelete(); $t->string('locale', 2);
            $t->json('snapshot'); $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $t->timestamps();
        });
        Schema::create('vehicles', function (Blueprint $t): void {
            $t->id(); $t->string('code', 40)->unique(); $t->foreignId('page_id')->constrained()->restrictOnDelete();
            $t->string('name'); $t->unsignedTinyInteger('passengers'); $t->string('image_key', 80);
            $t->unsignedTinyInteger('large_luggage')->nullable(); $t->unsignedTinyInteger('cabin_luggage')->nullable();
            $t->boolean('luggage_approved')->default(false); $t->boolean('active')->default(true); $t->unsignedSmallInteger('sort_order')->default(0); $t->timestamps();
        });
        Schema::create('media_assets', function (Blueprint $t): void {
            $t->id(); $t->string('key', 80)->unique(); $t->string('public_path')->nullable(); $t->string('upload_path')->nullable();
            $t->unsignedInteger('width')->nullable(); $t->unsignedInteger('height')->nullable(); $t->json('variants')->nullable();
            $t->string('alt_en', 255); $t->string('alt_it', 255); $t->string('alt_es', 255);
            $t->string('provenance')->nullable(); $t->timestamps();
        });
        Schema::create('inquiries', function (Blueprint $t): void {
            $t->id(); $t->string('reference', 40)->unique(); $t->string('submission_key', 64)->unique();
            $t->string('payload_fingerprint', 64); $t->string('type', 20)->index(); $t->string('locale', 2)->index();
            $t->string('status', 30)->default('new')->index(); $t->text('name'); $t->text('email'); $t->text('phone');
            $t->string('email_lookup', 64)->index(); $t->text('subject')->nullable(); $t->longText('message')->nullable();
            $t->string('page_key', 80)->nullable(); $t->string('service_key', 80)->nullable();
            $t->timestamp('follow_up_at')->nullable()->index(); $t->timestamp('closed_at')->nullable();
            $t->timestamp('privacy_notice_at'); $t->string('privacy_notice_version', 80); $t->timestamps();
        });
        Schema::create('inquiry_notes', function (Blueprint $t): void { $t->id(); $t->foreignId('inquiry_id')->constrained()->cascadeOnDelete(); $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $t->text('body'); $t->timestamps(); });
        Schema::create('notification_deliveries', function (Blueprint $t): void {
            $t->id(); $t->foreignId('inquiry_id')->constrained()->cascadeOnDelete(); $t->string('kind', 20);
            $t->string('status', 20)->default('pending')->index(); $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestamp('queued_at')->nullable(); $t->timestamp('smtp_accepted_at')->nullable(); $t->string('last_error_class')->nullable(); $t->timestamps();
            $t->unique(['inquiry_id', 'kind']);
        });
        Schema::create('settings', function (Blueprint $t): void { $t->string('key', 80)->primary(); $t->json('value')->nullable(); $t->timestamps(); });
        Schema::create('redirects', function (Blueprint $t): void { $t->id(); $t->string('locale', 2); $t->string('from_path', 190); $t->foreignId('page_id')->constrained()->cascadeOnDelete(); $t->timestamps(); $t->unique(['locale', 'from_path']); });
    }
    public function down(): void
    {
        foreach (['redirects', 'settings', 'notification_deliveries', 'inquiry_notes', 'inquiries', 'media_assets', 'vehicles', 'content_revisions', 'page_translations', 'pages', 'failed_jobs', 'jobs', 'cache_locks', 'cache', 'sessions', 'password_reset_tokens', 'users'] as $table) { Schema::dropIfExists($table); }
    }
};
