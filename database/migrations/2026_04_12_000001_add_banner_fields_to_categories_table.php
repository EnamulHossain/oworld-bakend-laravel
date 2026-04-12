<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'banner')) {
                $table->json('banner')->nullable()->after('image');
            }

            if (!Schema::hasColumn('categories', 'gallery_sort_order')) {
                $table->json('gallery_sort_order')->nullable()->after('banner');
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('categories', 'gallery_sort_order') ? 'gallery_sort_order' : null,
                Schema::hasColumn('categories', 'banner') ? 'banner' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
