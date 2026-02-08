<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (!Schema::hasColumn('offers', 'gallery_sort_order')) {
                $table->json('gallery_sort_order')->nullable()->after('images');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'gallery_sort_order')) {
                $table->json('gallery_sort_order')->nullable()->after('banner');
            }
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (Schema::hasColumn('offers', 'gallery_sort_order')) {
                $table->dropColumn('gallery_sort_order');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'gallery_sort_order')) {
                $table->dropColumn('gallery_sort_order');
            }
        });
    }
};
