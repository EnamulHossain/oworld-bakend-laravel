<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->json('subcategory_ids')->nullable()->after('subcategory_id'));
        Schema::table('organizations', fn (Blueprint $table) => $table->json('subcategory_ids')->nullable()->after('subcategory_id'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('subcategory_ids'));
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('subcategory_ids'));
    }
};
