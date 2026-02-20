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
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'area_ids')) {
                $table->json('area_ids')->nullable()->after('area_id');
            }
        });

        Schema::table('offers', function (Blueprint $table) {
            if (!Schema::hasColumn('offers', 'area_ids')) {
                $table->json('area_ids')->nullable()->after('area_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'area_ids')) {
                $table->dropColumn('area_ids');
            }
        });

        Schema::table('offers', function (Blueprint $table) {
            if (Schema::hasColumn('offers', 'area_ids')) {
                $table->dropColumn('area_ids');
            }
        });
    }
};
