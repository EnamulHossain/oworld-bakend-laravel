<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_post_id')->constrained('store_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['store_post_id', 'user_id']);
        });

        Schema::create('store_post_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_post_id')->constrained('store_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('body', 500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_post_comments');
        Schema::dropIfExists('store_post_likes');
    }
};
