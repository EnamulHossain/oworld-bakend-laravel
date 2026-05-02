<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('coouponn_details') && !Schema::hasTable('coupon_details')) {
            Schema::rename('coouponn_details', 'coupon_details');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('coupon_details') && !Schema::hasTable('coouponn_details')) {
            Schema::rename('coupon_details', 'coouponn_details');
        }
    }
};
