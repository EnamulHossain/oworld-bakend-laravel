<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['opening_hours', 'business_hours']);
        });

        Schema::create('organization_business_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'day_of_week']);
        });

        Schema::create('organization_facility', function (Blueprint $table) {
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['organization_id', 'facility_id']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->string('logo', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('organization_payment_method', function (Blueprint $table) {
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['organization_id', 'payment_method_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_payment_method');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('organization_facility');
        Schema::dropIfExists('organization_business_hours');

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('opening_hours', 120)->nullable();
            $table->json('business_hours')->nullable();
        });
    }
};
