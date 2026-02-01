<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('highlight_reels', 'parent_id')) {
            Schema::table('highlight_reels', function (Blueprint $table) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            });
        }
        if (Schema::hasColumn('highlight_reels', 'galleries')) {
            Schema::table('highlight_reels', function (Blueprint $table) {
                $table->dropColumn('galleries');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('highlight_reels', 'parent_id')) {
            Schema::table('highlight_reels', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('id');
                $table->foreign('parent_id')
                    ->references('id')
                    ->on('highlight_reels')
                    ->nullOnDelete();
            });
        }
        if (!Schema::hasColumn('highlight_reels', 'galleries')) {
            Schema::table('highlight_reels', function (Blueprint $table) {
                $table->json('galleries')->nullable()->after('thumbnail');
            });
        }
    }
};
