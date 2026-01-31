<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (!Schema::hasColumn('offers', 'offer_type')) {
                $table->enum('offer_type', ['regular', 'exclusive'])->default('regular')->after('event_id');
                $table->index(['offer_type', 'organization_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (Schema::hasColumn('offers', 'offer_type')) {
                $table->dropIndex(['offer_type', 'organization_id']);
                $table->dropColumn('offer_type');
            }
        });
    }
};
