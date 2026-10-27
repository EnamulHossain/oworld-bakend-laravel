<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('branch_managers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'manager_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('branch_managers'); }
};
