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
        if ($this->hasUniqueIndexOnCoupon()) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            $table->unique('coupon', 'coupons_coupon_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('coupons') || !$this->hasNamedIndex('coupons_coupon_unique')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropUnique('coupons_coupon_unique');
        });
    }

    private function hasUniqueIndexOnCoupon(): bool
    {
        if (!Schema::hasTable('coupons') || !Schema::hasColumn('coupons', 'coupon')) {
            return false;
        }

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'coupons')
            ->where('COLUMN_NAME', 'coupon')
            ->where('NON_UNIQUE', 0)
            ->exists();
    }

    private function hasNamedIndex(string $indexName): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'coupons')
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }
};
