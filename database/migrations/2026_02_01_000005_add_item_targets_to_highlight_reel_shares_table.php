<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('highlight_reel_shares', 'offer_id')) {
            Schema::table('highlight_reel_shares', function (Blueprint $table) {
                $table->unsignedBigInteger('offer_id')->nullable()->after('highlight_reel_id');
            });
        }
        if (!Schema::hasColumn('highlight_reel_shares', 'event_id')) {
            Schema::table('highlight_reel_shares', function (Blueprint $table) {
                $table->unsignedBigInteger('event_id')->nullable()->after('offer_id');
            });
        }
        if (!Schema::hasColumn('highlight_reel_shares', 'organization_id')) {
            Schema::table('highlight_reel_shares', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('event_id');
            });
        }

        $dbName = DB::getDatabaseName();
        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$dbName, 'highlight_reel_shares']
        );
        $constraintNames = array_map(fn ($row) => $row->CONSTRAINT_NAME, $constraints);

        Schema::table('highlight_reel_shares', function (Blueprint $table) use ($constraintNames) {
            if (Schema::hasColumn('highlight_reel_shares', 'offer_id')
                && !in_array('highlight_reel_shares_offer_id_foreign', $constraintNames, true)) {
                $table->foreign('offer_id')->references('id')->on('offers')->nullOnDelete();
            }
            if (Schema::hasColumn('highlight_reel_shares', 'event_id')
                && !in_array('highlight_reel_shares_event_id_foreign', $constraintNames, true)) {
                $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
            }
            if (Schema::hasColumn('highlight_reel_shares', 'organization_id')
                && !in_array('highlight_reel_shares_organization_id_foreign', $constraintNames, true)) {
                $table->foreign('organization_id')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('highlight_reel_shares', function (Blueprint $table) {
            $table->dropForeign(['offer_id']);
            $table->dropForeign(['event_id']);
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['offer_id', 'event_id', 'organization_id']);
        });
    }
};
