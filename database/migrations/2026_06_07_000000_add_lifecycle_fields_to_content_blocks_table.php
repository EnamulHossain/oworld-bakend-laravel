<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_blocks', function (Blueprint $table) {
            $table->string('status', 30)->default('published')->index()->after('description');
            $table->date('start_date')->nullable()->after('status');
            $table->time('start_time')->nullable()->after('start_date');
            $table->date('end_date')->nullable()->after('start_time');
            $table->time('end_time')->nullable()->after('end_date');
            $table->date('expiration_date')->nullable()->after('end_time');
            $table->time('expiration_time')->nullable()->after('expiration_date');
        });

        DB::table('content_blocks')->where('is_active', false)->update(['status' => 'draft']);
    }

    public function down(): void
    {
        Schema::table('content_blocks', function (Blueprint $table) {
            $table->dropColumn([
                'status',
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
