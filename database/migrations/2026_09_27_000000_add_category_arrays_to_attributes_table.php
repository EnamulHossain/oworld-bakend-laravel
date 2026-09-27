<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->json('category_ids')->nullable();
            $table->json('subcategory_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attributes', fn (Blueprint $table) => $table->dropColumn(['category_ids', 'subcategory_ids']));
    }
};
