<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->json('category_types')->nullable()->after('is_event_category');
        });

        DB::table('categories')->where('is_event_category', true)
            ->update(['category_types' => json_encode(['event'])]);
        DB::table('categories')->where('is_event_category', false)
            ->update(['category_types' => json_encode(['offer', 'store'])]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('category_types');
        });
    }
};
