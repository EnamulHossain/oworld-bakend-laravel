<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name', 120)->index();
            $table->string('page', 80)->nullable()->index();
            $table->string('action', 80)->nullable()->index();
            $table->string('filter', 80)->nullable()->index();
            $table->string('channel', 80)->nullable();
            $table->unsignedBigInteger('highlight_id')->nullable()->index();
            $table->unsignedBigInteger('offer_id')->nullable()->index();
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('client_session_id', 120)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
