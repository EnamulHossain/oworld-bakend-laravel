<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('content_block_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_block_id')->constrained('content_blocks')->cascadeOnDelete();
            $table->enum('type', ['category', 'event', 'offer'])->default('category');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('title', 200)->nullable();
            $table->string('subtitle', 255)->nullable();
            $table->string('image', 500)->nullable();
            $table->string('external_link', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['content_block_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_block_items');
    }
};
