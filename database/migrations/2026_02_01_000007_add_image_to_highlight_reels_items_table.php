<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('highlight_reels_items', 'image')) {
            Schema::table('highlight_reels_items', function (Blueprint $table) {
                $table->string('image')->nullable()->after('organization_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('highlight_reels_items', 'image')) {
            Schema::table('highlight_reels_items', function (Blueprint $table) {
                $table->dropColumn('image');
            });
        }
    }
};
