<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('address', 500)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('google_map_url', 500)->nullable();
            $table->string('opening_hours', 120)->nullable();
            $table->enum('status', ['open', 'temporarily_closed', 'relocating', 'coming_soon'])->default('open');
            $table->boolean('delivery_available')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_branches');
    }
};
