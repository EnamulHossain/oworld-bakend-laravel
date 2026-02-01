<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('highlight_reel_reactions', 'offer_id')) {
            Schema::table('highlight_reel_reactions', function (Blueprint $table) {
                $table->unsignedBigInteger('offer_id')->nullable()->after('highlight_reel_id');
            });
        }
        if (!Schema::hasColumn('highlight_reel_reactions', 'event_id')) {
            Schema::table('highlight_reel_reactions', function (Blueprint $table) {
                $table->unsignedBigInteger('event_id')->nullable()->after('offer_id');
            });
        }
        if (!Schema::hasColumn('highlight_reel_reactions', 'organization_id')) {
            Schema::table('highlight_reel_reactions', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('event_id');
            });
        }

        $dbName = DB::getDatabaseName();
        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$dbName, 'highlight_reel_reactions']
        );
        $constraintNames = array_map(fn ($row) => $row->CONSTRAINT_NAME, $constraints);
        if (in_array('highlight_reel_reactions_highlight_reel_id_foreign', $constraintNames, true)) {
            Schema::table('highlight_reel_reactions', function (Blueprint $table) {
                $table->dropForeign(['highlight_reel_id']);
            });
        }
        if (in_array('highlight_reel_reactions_user_id_foreign', $constraintNames, true)) {
            Schema::table('highlight_reel_reactions', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        }

        $indexes = DB::select("SHOW INDEX FROM highlight_reel_reactions");
        $indexNames = array_map(fn ($row) => $row->Key_name, $indexes);
        if (in_array('highlight_reel_reactions_highlight_reel_id_user_id_unique', $indexNames, true)) {
            Schema::table('highlight_reel_reactions', function (Blueprint $table) {
                $table->dropUnique('highlight_reel_reactions_highlight_reel_id_user_id_unique');
            });
        }

        Schema::table('highlight_reel_reactions', function (Blueprint $table) {
            $table->foreign('highlight_reel_id')->references('id')->on('highlight_reels')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('offer_id')->references('id')->on('offers')->nullOnDelete();
            $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
            $table->foreign('organization_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['highlight_reel_id', 'user_id', 'offer_id', 'event_id', 'organization_id'], 'highlight_reel_reactions_target_unique');
        });
    }

    public function down(): void
    {
        Schema::table('highlight_reel_reactions', function (Blueprint $table) {
            $table->dropUnique('highlight_reel_reactions_target_unique');
            $table->unique(['highlight_reel_id', 'user_id']);
            $table->dropForeign(['offer_id']);
            $table->dropForeign(['event_id']);
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['offer_id', 'event_id', 'organization_id']);
        });
    }
};
