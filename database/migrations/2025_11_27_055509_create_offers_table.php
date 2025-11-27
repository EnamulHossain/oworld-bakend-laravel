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
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->text('details')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->enum('discount_type', ['percentage', 'flat', 'bogo', 'custom'])->default('percentage');
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->string('thumbnail', 500)->nullable();
            $table->string('cover', 500)->nullable();
            $table->json('images')->nullable();
            $table->json('videos')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('organization_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->enum('offer_type', ['general', 'category', 'event', 'special'])->default('general');
            $table->enum('status', ['draft', 'active', 'inactive', 'expired'])->default('draft')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['offer_type', 'organization_id']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
