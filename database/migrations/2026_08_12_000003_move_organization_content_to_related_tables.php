<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'owner_full_name',
                'owner_phone',
                'owner_email',
                'nid_no',
                'trade_license_no',
                'trade_license_valid_until',
                'organization_valid_until',
                'nid_front_image',
                'nid_back_image',
                'trade_license_image',
                'interior_media',
                'payment_methods',
                'facilities',
                'highlights',
            ]);
        });

        Schema::create('organization_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained('organizations')->cascadeOnDelete();
            $table->string('owner_full_name', 120);
            $table->string('owner_phone', 30);
            $table->string('owner_email');
            $table->string('nid_no', 50);
            $table->string('trade_license_no', 100);
            $table->date('trade_license_valid_until');
            $table->date('organization_valid_until')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('verification_id')->nullable()->constrained('organization_verifications')->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('disk', 50)->default('local');
            $table->string('file_path', 500);
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('ocr_status', 30)->default('not_started');
            $table->json('ocr_data')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'document_type']);
            $table->index(['verification_id', 'document_type']);
        });

        Schema::create('organization_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('type', 30)->index();
            $table->string('category', 100)->nullable();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->char('currency', 3)->default('BDT');
            $table->boolean('is_available')->default(true)->index();
            $table->boolean('is_pinned')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'type', 'sort_order']);
        });

        Schema::create('organization_catalog_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_item_id')->constrained('organization_catalog_items')->cascadeOnDelete();
            $table->string('media_type', 20)->default('image');
            $table->string('file_path', 500);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['catalog_item_id', 'sort_order']);
        });

        Schema::create('organization_about_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('title', 150)->nullable();
            $table->longText('content')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'sort_order']);
        });

        Schema::create('organization_about_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('about_section_id')->nullable()->constrained('organization_about_sections')->cascadeOnDelete();
            $table->string('media_type', 20)->default('image');
            $table->string('file_path', 500);
            $table->string('alt_text', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_about_media');
        Schema::dropIfExists('organization_about_sections');
        Schema::dropIfExists('organization_catalog_media');
        Schema::dropIfExists('organization_catalog_items');
        Schema::dropIfExists('organization_documents');
        Schema::dropIfExists('organization_verifications');

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('owner_full_name', 120)->nullable();
            $table->string('owner_phone', 30)->nullable();
            $table->string('owner_email')->nullable();
            $table->string('nid_no', 50)->nullable();
            $table->string('trade_license_no', 100)->nullable();
            $table->date('trade_license_valid_until')->nullable();
            $table->date('organization_valid_until')->nullable();
            $table->string('nid_front_image', 500)->nullable();
            $table->string('nid_back_image', 500)->nullable();
            $table->string('trade_license_image', 500)->nullable();
            $table->json('interior_media')->nullable();
            $table->json('payment_methods')->nullable();
            $table->json('facilities')->nullable();
            $table->json('highlights')->nullable();
        });
    }
};
