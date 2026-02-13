<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (!Schema::hasColumn('offers', 'start_time')) {
                $table->time('start_time')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('offers', 'end_time')) {
                $table->time('end_time')->nullable()->after('end_date');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'start_time')) {
                $table->time('start_time')->nullable()->after('starting_date');
            }
            if (!Schema::hasColumn('events', 'end_time')) {
                $table->time('end_time')->nullable()->after('end_date');
            }
        });

        // Backfill time columns from legacy datetime values.
        DB::statement("
            UPDATE offers
            SET
                start_time = CASE
                    WHEN TIME(start_date) = '00:00:00' THEN NULL
                    ELSE TIME(start_date)
                END,
                end_time = CASE
                    WHEN TIME(end_date) IN ('00:00:00', '23:59:59') THEN NULL
                    ELSE TIME(end_date)
                END
        ");

        DB::statement("
            UPDATE events
            SET
                start_time = CASE
                    WHEN TIME(starting_date) = '00:00:00' THEN NULL
                    ELSE TIME(starting_date)
                END,
                end_time = CASE
                    WHEN TIME(end_date) IN ('00:00:00', '23:59:59') THEN NULL
                    ELSE TIME(end_date)
                END
        ");

        // Convert datetime columns to date-only columns.
        DB::statement("ALTER TABLE offers MODIFY start_date DATE NULL");
        DB::statement("ALTER TABLE offers MODIFY end_date DATE NULL");
        DB::statement("ALTER TABLE events MODIFY starting_date DATE NULL");
        DB::statement("ALTER TABLE events MODIFY end_date DATE NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE offers MODIFY start_date DATETIME NULL");
        DB::statement("ALTER TABLE offers MODIFY end_date DATETIME NULL");
        DB::statement("ALTER TABLE events MODIFY starting_date DATETIME NULL");
        DB::statement("ALTER TABLE events MODIFY end_date DATETIME NULL");

        DB::statement("
            UPDATE offers
            SET
                start_date = CASE
                    WHEN start_date IS NULL THEN NULL
                    WHEN start_time IS NULL THEN CONCAT(start_date, ' 00:00:00')
                    ELSE CONCAT(start_date, ' ', start_time)
                END,
                end_date = CASE
                    WHEN end_date IS NULL THEN NULL
                    WHEN end_time IS NULL THEN CONCAT(end_date, ' 23:59:59')
                    ELSE CONCAT(end_date, ' ', end_time)
                END
        ");

        DB::statement("
            UPDATE events
            SET
                starting_date = CASE
                    WHEN starting_date IS NULL THEN NULL
                    WHEN start_time IS NULL THEN CONCAT(starting_date, ' 00:00:00')
                    ELSE CONCAT(starting_date, ' ', start_time)
                END,
                end_date = CASE
                    WHEN end_date IS NULL THEN NULL
                    WHEN end_time IS NULL THEN CONCAT(end_date, ' 23:59:59')
                    ELSE CONCAT(end_date, ' ', end_time)
                END
        ");

        Schema::table('offers', function (Blueprint $table) {
            if (Schema::hasColumn('offers', 'start_time')) {
                $table->dropColumn('start_time');
            }
            if (Schema::hasColumn('offers', 'end_time')) {
                $table->dropColumn('end_time');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'start_time')) {
                $table->dropColumn('start_time');
            }
            if (Schema::hasColumn('events', 'end_time')) {
                $table->dropColumn('end_time');
            }
        });
    }
};

