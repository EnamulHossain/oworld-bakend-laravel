<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('coupons', 'modal_image')) {
                $table->string('modal_image', 500)->nullable()->after('description');
            }
            if (!Schema::hasColumn('coupons', 'modal_title')) {
                $table->string('modal_title', 255)->nullable()->after('modal_image');
            }
            if (!Schema::hasColumn('coupons', 'modal_main_text')) {
                $table->text('modal_main_text')->nullable()->after('modal_title');
            }
            if (!Schema::hasColumn('coupons', 'modal_sub_text')) {
                $table->text('modal_sub_text')->nullable()->after('modal_main_text');
            }
            if (!Schema::hasColumn('coupons', 'modal_placeholder_text')) {
                $table->string('modal_placeholder_text', 255)->nullable()->after('modal_sub_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            foreach (['modal_placeholder_text', 'modal_sub_text', 'modal_main_text', 'modal_title', 'modal_image'] as $column) {
                if (Schema::hasColumn('coupons', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
