<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_block_items', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('external_link');
            $table->time('start_time')->nullable()->after('start_date');
            $table->date('end_date')->nullable()->after('start_time');
            $table->time('end_time')->nullable()->after('end_date');
            $table->date('expiration_date')->nullable()->after('end_time');
            $table->time('expiration_time')->nullable()->after('expiration_date');
        });
    }

    public function down(): void
    {
        Schema::table('content_block_items', function (Blueprint $table) {
            $table->dropColumn([
                'start_date',
                'start_time',
                'end_date',
                'end_time',
                'expiration_date',
                'expiration_time',
            ]);
        });
    }
};
