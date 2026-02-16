<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('coupons', 'store_id') && !Schema::hasColumn('coupons', 'organization_id')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->after('event_id')->constrained('users')->nullOnDelete();
            });

            DB::statement('UPDATE coupons SET organization_id = store_id WHERE organization_id IS NULL');

            Schema::table('coupons', function (Blueprint $table) {
                $table->dropForeign(['store_id']);
                $table->dropColumn('store_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('coupons', 'organization_id') && !Schema::hasColumn('coupons', 'store_id')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->foreignId('store_id')->nullable()->after('event_id')->constrained('users')->nullOnDelete();
            });

            DB::statement('UPDATE coupons SET store_id = organization_id WHERE store_id IS NULL');

            Schema::table('coupons', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            });
        }
    }
};