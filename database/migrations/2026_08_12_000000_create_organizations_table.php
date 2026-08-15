<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();

            $table->string('name');
            $table->string('business_type', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('address')->nullable();

            $table->string('facebook_url', 500)->nullable();
            $table->string('instagram_url', 500)->nullable();
            $table->string('website_url', 500)->nullable();
            $table->string('google_map_url', 500)->nullable();

            $table->string('logo', 500)->nullable();
            $table->string('profile_banner', 500)->nullable();

            $table->string('status', 30)->default('active')->index();
            $table->string('verification_status', 30)->default('not_submitted')->index();
            $table->boolean('is_verified')->default(false)->index();

            $table->unsignedInteger('follower_count')->default(0);
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->unsignedInteger('review_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_organization_id', 'status']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
