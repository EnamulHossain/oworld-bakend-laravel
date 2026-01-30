<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('highlight_reel_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('highlight_reel_id');
            $table->unsignedBigInteger('user_id');
            $table->string('reaction', 20);
            $table->timestamps();

            $table->foreign('highlight_reel_id')->references('id')->on('highlight_reels')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['highlight_reel_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('highlight_reel_reactions');
    }
};
