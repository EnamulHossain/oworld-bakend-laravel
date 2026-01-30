<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('highlight_reel_shares', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('highlight_reel_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('channel', 50)->nullable();
            $table->timestamps();

            $table->foreign('highlight_reel_id')->references('id')->on('highlight_reels')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('highlight_reel_shares');
    }
};
