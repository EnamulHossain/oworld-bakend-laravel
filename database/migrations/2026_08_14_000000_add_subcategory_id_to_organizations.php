<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('subcategory_id')->nullable()->after('categories')->constrained('categories')->nullOnDelete();
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('subcategory_id')->nullable()->after('category_id')->constrained('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', fn (Blueprint $table) => $table->dropConstrainedForeignId('subcategory_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('subcategory_id'));
    }
};
