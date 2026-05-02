<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('coupons', 'expiration_date')) {
                $table->date('expiration_date')->nullable()->after('end_time');
            }
            if (!Schema::hasColumn('coupons', 'expiration_time')) {
                $table->time('expiration_time')->nullable()->after('expiration_date');
            }
        });

        DB::statement("ALTER TABLE coupons MODIFY status VARCHAR(30) NOT NULL DEFAULT 'draft'");

        $this->normalizeDuplicateNames();
        $this->addUniqueNameIndex();
    }

    public function down(): void
    {
        $this->dropUniqueNameIndex();

        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'expiration_time')) {
                $table->dropColumn('expiration_time');
            }
            if (Schema::hasColumn('coupons', 'expiration_date')) {
                $table->dropColumn('expiration_date');
            }
        });
    }

    private function addUniqueNameIndex(): void
    {
        if ($this->hasNamedIndex('coupons_name_unique')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            $table->unique('name', 'coupons_name_unique');
        });
    }

    private function normalizeDuplicateNames(): void
    {
        if (!Schema::hasTable('coupons') || !Schema::hasColumn('coupons', 'name')) {
            return;
        }

        DB::table('coupons')
            ->select('name', DB::raw('COUNT(*) as total'))
            ->groupBy('name')
            ->having('total', '>', 1)
            ->orderBy('name')
            ->get()
            ->each(function ($row) {
                DB::table('coupons')
                    ->where('name', $row->name)
                    ->orderBy('id')
                    ->pluck('id')
                    ->skip(1)
                    ->each(function ($id) use ($row) {
                        $base = trim((string) $row->name) ?: 'Coupon';
                        $suffix = '-' . $id;
                        $nextName = substr($base, 0, max(1, 30 - strlen($suffix))) . $suffix;
                        DB::table('coupons')->where('id', $id)->update(['name' => $nextName]);
                    });
            });
    }

    private function dropUniqueNameIndex(): void
    {
        if (!$this->hasNamedIndex('coupons_name_unique')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropUnique('coupons_name_unique');
        });
    }

    private function hasNamedIndex(string $indexName): bool
    {
        if (!Schema::hasTable('coupons')) {
            return false;
        }

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'coupons')
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }
};
