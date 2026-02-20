<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('highlight_reels_items', 'video')) {
            Schema::table('highlight_reels_items', function (Blueprint $table) {
                $table->string('video')->nullable()->after('image');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('highlight_reels_items', 'video')) {
            Schema::table('highlight_reels_items', function (Blueprint $table) {
                $table->dropColumn('video');
            });
        }
    }
};
