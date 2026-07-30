<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['update', 'announcement', 'offer', 'event', 'new_arrival', 'promotion'])->default('update');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('image', 500)->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->unsignedInteger('pin_order')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'is_pinned', 'pin_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_posts');
    }
};
