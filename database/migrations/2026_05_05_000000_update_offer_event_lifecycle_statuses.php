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
            if (!Schema::hasColumn('offers', 'expiration_date')) {
                $table->date('expiration_date')->nullable()->after('end_time');
            }
            if (!Schema::hasColumn('offers', 'expiration_time')) {
                $table->time('expiration_time')->nullable()->after('expiration_date');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'expiration_date')) {
                $table->date('expiration_date')->nullable()->after('end_time');
            }
            if (!Schema::hasColumn('events', 'expiration_time')) {
                $table->time('expiration_time')->nullable()->after('expiration_date');
            }
        });

        DB::statement("ALTER TABLE offers MODIFY status VARCHAR(30) NOT NULL DEFAULT 'draft'");
        DB::statement("ALTER TABLE events MODIFY status VARCHAR(30) NOT NULL DEFAULT 'draft'");

        DB::table('offers')->where('status', 'active')->update(['status' => 'published']);
        DB::table('offers')->where('status', 'inactive')->update(['status' => 'archived']);
        DB::table('events')->where('status', 'cancelled')->update(['status' => 'canceled']);
        DB::table('events')->where('status', 'completed')->update(['status' => 'expired']);
    }

    public function down(): void
    {
        DB::table('offers')->where('status', 'published')->update(['status' => 'active']);
        DB::table('offers')->where('status', 'scheduled')->update(['status' => 'draft']);
        DB::table('offers')->where('status', 'archived')->update(['status' => 'inactive']);
        DB::table('events')->where('status', 'canceled')->update(['status' => 'cancelled']);
        DB::table('events')->where('status', 'expired')->update(['status' => 'completed']);
        DB::table('events')->where('status', 'scheduled')->update(['status' => 'draft']);

        Schema::table('offers', function (Blueprint $table) {
            if (Schema::hasColumn('offers', 'expiration_time')) {
                $table->dropColumn('expiration_time');
            }
            if (Schema::hasColumn('offers', 'expiration_date')) {
                $table->dropColumn('expiration_date');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'expiration_time')) {
                $table->dropColumn('expiration_time');
            }
            if (Schema::hasColumn('events', 'expiration_date')) {
                $table->dropColumn('expiration_date');
            }
        });
    }
};
