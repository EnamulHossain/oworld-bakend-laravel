<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->json('category_ids')->nullable()->after('category_id');
            $table->json('subcategory_ids')->nullable()->after('subcategory_id');
        });
    }

    public function down(): void
    {
        Schema::table('offers', fn (Blueprint $table) => $table->dropColumn(['category_ids', 'subcategory_ids']));
    }
};
