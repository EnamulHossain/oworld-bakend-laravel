<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false);
            $table->date('recurring_start_date')->nullable();
            $table->date('recurring_end_date')->nullable();
            $table->json('recurring_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn([
            'is_recurring', 'recurring_start_date', 'recurring_end_date', 'recurring_days',
        ]));
    }
};
