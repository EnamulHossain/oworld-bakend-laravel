<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('highlight_reels', 'description')) {
            Schema::table('highlight_reels', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('highlight_reels', 'description')) {
            Schema::table('highlight_reels', function (Blueprint $table) {
                $table->text('description')->nullable()->after('title');
            });
        }
    }
};
