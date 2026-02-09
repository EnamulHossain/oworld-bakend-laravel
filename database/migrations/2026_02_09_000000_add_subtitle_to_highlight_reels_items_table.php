<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('highlight_reels_items', function (Blueprint $table) {
            if (!Schema::hasColumn('highlight_reels_items', 'subtitle')) {
                $table->string('subtitle', 255)->nullable()->after('title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('highlight_reels_items', function (Blueprint $table) {
            if (Schema::hasColumn('highlight_reels_items', 'subtitle')) {
                $table->dropColumn('subtitle');
            }
        });
    }
};

