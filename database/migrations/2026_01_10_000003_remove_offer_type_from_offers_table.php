<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('offers', 'offer_type')) {
            return;
        }

        Schema::table('offers', function (Blueprint $table) {
            $table->dropIndex(['offer_type', 'organization_id']);
            $table->dropColumn('offer_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('offers', 'offer_type')) {
            return;
        }

        Schema::table('offers', function (Blueprint $table) {
            $table
                ->enum('offer_type', [
                    'general',
                    'category',
                    'event',
                    'special',
                    'bogo',
                    'discount',
                    'combo',
                    'happy_hour',
                    'lunch_hour',
                    'late_night',
                    'complimentary',
                ])
                ->default('special')
                ->after('event_id');
            $table->index(['offer_type', 'organization_id']);
        });
    }
};
