<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_branch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->foreignId('organization_branch_id')->constrained('organization_branches')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['offer_id', 'organization_branch_id'], 'offer_branch_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_branch');
    }
};
