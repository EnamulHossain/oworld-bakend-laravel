<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('highlight_reel_reactions', 'highlight_reel_item_id')) {
            Schema::table('highlight_reel_reactions', function (Blueprint $table) {
                $table->unsignedBigInteger('highlight_reel_item_id')->nullable()->after('highlight_reel_id');
            });
        }

        $indexes = DB::select('SHOW INDEX FROM highlight_reel_reactions');
        $indexNames = array_map(fn ($row) => $row->Key_name, $indexes);

        if (!in_array('highlight_reel_reactions_highlight_id_index', $indexNames, true)) {
            Schema::table('highlight_reel_reactions', function (Blueprint $table) {
                $table->index('highlight_reel_id', 'highlight_reel_reactions_highlight_id_index');
            });
        }

        if (in_array('highlight_reel_reactions_target_unique', $indexNames, true)) {
            Schema::table('highlight_reel_reactions', function (Blueprint $table) {
                $table->dropUnique('highlight_reel_reactions_target_unique');
            });
        }

        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [DB::getDatabaseName(), 'highlight_reel_reactions', 'highlight_reel_reactions_item_foreign']
        );

        if (empty($constraints)) {
            Schema::table('highlight_reel_reactions', function (Blueprint $table) {
                $table->foreign('highlight_reel_item_id', 'highlight_reel_reactions_item_foreign')
                    ->references('id')
                    ->on('highlight_reels_items')
                    ->nullOnDelete();
            });
        }

        Schema::table('highlight_reel_reactions', function (Blueprint $table) {
            $table->unique(
                ['highlight_reel_id', 'highlight_reel_item_id', 'user_id', 'offer_id', 'event_id', 'organization_id'],
                'highlight_reel_reactions_item_target_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('highlight_reel_reactions', function (Blueprint $table) {
            $table->dropUnique('highlight_reel_reactions_item_target_unique');
            $table->dropForeign('highlight_reel_reactions_item_foreign');
            $table->dropColumn('highlight_reel_item_id');
            $table->unique(
                ['highlight_reel_id', 'user_id', 'offer_id', 'event_id', 'organization_id'],
                'highlight_reel_reactions_target_unique'
            );
        });
    }
};
