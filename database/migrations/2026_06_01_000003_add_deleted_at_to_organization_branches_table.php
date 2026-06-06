<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_branches', function (Blueprint $table) {
            if (!Schema::hasColumn('organization_branches', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organization_branches', function (Blueprint $table) {
            if (Schema::hasColumn('organization_branches', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
