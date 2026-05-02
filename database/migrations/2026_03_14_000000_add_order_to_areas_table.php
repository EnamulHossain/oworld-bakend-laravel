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
        if (!Schema::hasColumn('areas', 'order')) {
            Schema::table('areas', function (Blueprint $table) {
                $table->integer('order')->default(0)->after('name');
                $table->index('order');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('areas', 'order')) {
            Schema::table('areas', function (Blueprint $table) {
                $table->dropIndex(['order']);
                $table->dropColumn('order');
            });
        }
    }
};

