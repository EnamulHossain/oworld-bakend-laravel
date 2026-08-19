<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_posts')) {
            DB::statement("ALTER TABLE store_posts MODIFY type ENUM('general','menu','update','announcement','offer','event','new_arrival','promotion') NOT NULL DEFAULT 'general'");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('store_posts')) {
            DB::table('store_posts')->where('type', 'menu')->update(['type' => 'general']);
            DB::statement("ALTER TABLE store_posts MODIFY type ENUM('general','update','announcement','offer','event','new_arrival','promotion') NOT NULL DEFAULT 'general'");
        }
    }
};
