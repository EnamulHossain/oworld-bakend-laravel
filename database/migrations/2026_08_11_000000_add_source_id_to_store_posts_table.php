<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->nullable()->after('type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('store_posts', function (Blueprint $table) {
            $table->dropColumn('source_id');
        });
    }
};
