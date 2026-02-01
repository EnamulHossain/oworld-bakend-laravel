<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('highlight_reels', 'offer_id')) {
            Schema::table('highlight_reels', function (Blueprint $table) {
                $table->dropForeign(['offer_id']);
                $table->dropColumn('offer_id');
            });
        }
        if (Schema::hasColumn('highlight_reels', 'event_id')) {
            Schema::table('highlight_reels', function (Blueprint $table) {
                $table->dropForeign(['event_id']);
                $table->dropColumn('event_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('highlight_reels', 'offer_id')) {
            Schema::table('highlight_reels', function (Blueprint $table) {
                $table->unsignedBigInteger('offer_id')->nullable()->after('external_link');
                $table->foreign('offer_id')->references('id')->on('offers')->nullOnDelete();
            });
        }
        if (!Schema::hasColumn('highlight_reels', 'event_id')) {
            Schema::table('highlight_reels', function (Blueprint $table) {
                $table->unsignedBigInteger('event_id')->nullable()->after('offer_id');
                $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
            });
        }
    }
};
