<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            if (!Schema::hasColumn('attributes', 'start_date')) {
                $table->date('start_date')->nullable()->after('category_id');
            }
            if (!Schema::hasColumn('attributes', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('attributes', 'auto_expires')) {
                $table->boolean('auto_expires')->default(true)->after('end_date');
            }
        });

        DB::table('attributes')
            ->whereNull('start_date')
            ->update(['start_date' => now('Asia/Dhaka')->toDateString()]);

        DB::statement("ALTER TABLE attributes MODIFY status ENUM('active', 'published', 'draft', 'inactive', 'expired') NOT NULL DEFAULT 'published'");

        DB::table('attributes')->where('status', 'active')->update(['status' => 'published']);
        DB::table('attributes')->where('status', 'inactive')->update(['status' => 'draft']);

        DB::statement("ALTER TABLE attributes MODIFY status ENUM('published', 'draft', 'expired') NOT NULL DEFAULT 'published'");
    }

    public function down(): void
    {
        DB::table('attributes')->where('status', 'published')->update(['status' => 'active']);
        DB::table('attributes')->where('status', 'expired')->update(['status' => 'inactive']);

        DB::statement("ALTER TABLE attributes MODIFY status ENUM('active', 'draft', 'inactive') NOT NULL DEFAULT 'active'");

        Schema::table('attributes', function (Blueprint $table) {
            if (Schema::hasColumn('attributes', 'auto_expires')) {
                $table->dropColumn('auto_expires');
            }
            if (Schema::hasColumn('attributes', 'end_date')) {
                $table->dropColumn('end_date');
            }
            if (Schema::hasColumn('attributes', 'start_date')) {
                $table->dropColumn('start_date');
            }
        });
    }
};
