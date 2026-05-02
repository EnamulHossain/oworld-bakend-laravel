<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (!Schema::hasColumn('offers', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false)->after('offer_type');
            }

            if (!Schema::hasColumn('offers', 'recurring_start_date')) {
                $table->date('recurring_start_date')->nullable()->after('is_recurring');
            }

            if (!Schema::hasColumn('offers', 'recurring_end_date')) {
                $table->date('recurring_end_date')->nullable()->after('recurring_start_date');
            }

            if (!Schema::hasColumn('offers', 'recurring_day_interval')) {
                $table->unsignedInteger('recurring_day_interval')->nullable()->after('recurring_end_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('offers', 'recurring_day_interval') ? 'recurring_day_interval' : null,
                Schema::hasColumn('offers', 'recurring_end_date') ? 'recurring_end_date' : null,
                Schema::hasColumn('offers', 'recurring_start_date') ? 'recurring_start_date' : null,
                Schema::hasColumn('offers', 'is_recurring') ? 'is_recurring' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
