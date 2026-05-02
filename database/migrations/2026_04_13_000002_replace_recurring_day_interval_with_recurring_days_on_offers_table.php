<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (!Schema::hasColumn('offers', 'recurring_days')) {
                $table->json('recurring_days')->nullable()->after('recurring_end_date');
            }
        });

        Schema::table('offers', function (Blueprint $table) {
            if (Schema::hasColumn('offers', 'recurring_day_interval')) {
                $table->dropColumn('recurring_day_interval');
            }
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (!Schema::hasColumn('offers', 'recurring_day_interval')) {
                $table->unsignedInteger('recurring_day_interval')->nullable()->after('recurring_end_date');
            }
        });

        Schema::table('offers', function (Blueprint $table) {
            if (Schema::hasColumn('offers', 'recurring_days')) {
                $table->dropColumn('recurring_days');
            }
        });
    }
};
