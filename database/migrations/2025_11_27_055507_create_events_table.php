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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->json('banner')->nullable();
            $table->enum('status', ['draft', 'published', 'cancelled', 'completed'])->default('draft')->index();
            $table->dateTime('starting_date');
            $table->dateTime('end_date');
            $table->string('location', 255)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['starting_date', 'end_date']);
            $table->index(['category_id', 'organization_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
