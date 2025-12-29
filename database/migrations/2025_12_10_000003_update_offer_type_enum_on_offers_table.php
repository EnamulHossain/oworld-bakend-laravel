<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE offers MODIFY COLUMN offer_type ENUM('general', 'category', 'event', 'special', 'bogo', 'discount', 'combo', 'happy_hour', 'lunch_hour', 'late_night', 'complimentary') DEFAULT 'special'"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE offers MODIFY COLUMN offer_type ENUM('general', 'category', 'event', 'special') DEFAULT 'general'"
        );
    }
};
