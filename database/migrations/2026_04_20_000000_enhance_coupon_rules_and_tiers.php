<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('coupons', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (!Schema::hasColumn('coupons', 'campaign_type')) {
                $table->enum('campaign_type', ['standard', 'tiered', 'referral'])->default('standard')->after('description');
            }
            if (!Schema::hasColumn('coupons', 'usage_limit_per_user')) {
                $table->unsignedInteger('usage_limit_per_user')->nullable()->after('total_coupon');
            }
        });

        if (!Schema::hasTable('coupon_tiers')) {
            Schema::create('coupon_tiers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
                $table->string('label', 120)->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->enum('discount_type', ['percentage', 'flat']);
                $table->decimal('discount_value', 10, 2);
                $table->decimal('max_discount_amount', 10, 2)->nullable();
                $table->decimal('min_order_amount', 10, 2)->nullable();
                $table->unsignedInteger('referral_required_count')->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        Schema::table('coupon_details', function (Blueprint $table) {
            if (!Schema::hasColumn('coupon_details', 'coupon_tier_id')) {
                $table->foreignId('coupon_tier_id')->nullable()->after('coupon_id')->constrained('coupon_tiers')->nullOnDelete();
            }
            if (!Schema::hasColumn('coupon_details', 'discount_type')) {
                $table->enum('discount_type', ['percentage', 'flat'])->nullable()->after('organization_id');
            }
            if (!Schema::hasColumn('coupon_details', 'discount_value')) {
                $table->decimal('discount_value', 10, 2)->nullable()->after('discount_type');
            }
            if (!Schema::hasColumn('coupon_details', 'max_discount_amount')) {
                $table->decimal('max_discount_amount', 10, 2)->nullable()->after('discount_value');
            }
            if (!Schema::hasColumn('coupon_details', 'min_order_amount')) {
                $table->decimal('min_order_amount', 10, 2)->nullable()->after('max_discount_amount');
            }
            if (!Schema::hasColumn('coupon_details', 'referral_required_count')) {
                $table->unsignedInteger('referral_required_count')->default(0)->after('min_order_amount');
            }
            if (!Schema::hasColumn('coupon_details', 'claimed_by_user_id')) {
                $table->foreignId('claimed_by_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('coupon_details', 'claimed_at')) {
                $table->dateTime('claimed_at')->nullable()->after('claimed_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupon_details', function (Blueprint $table) {
            if (Schema::hasColumn('coupon_details', 'claimed_at')) {
                $table->dropColumn('claimed_at');
            }
            if (Schema::hasColumn('coupon_details', 'claimed_by_user_id')) {
                $table->dropForeign(['claimed_by_user_id']);
                $table->dropColumn('claimed_by_user_id');
            }
            if (Schema::hasColumn('coupon_details', 'referral_required_count')) {
                $table->dropColumn('referral_required_count');
            }
            if (Schema::hasColumn('coupon_details', 'min_order_amount')) {
                $table->dropColumn('min_order_amount');
            }
            if (Schema::hasColumn('coupon_details', 'max_discount_amount')) {
                $table->dropColumn('max_discount_amount');
            }
            if (Schema::hasColumn('coupon_details', 'discount_value')) {
                $table->dropColumn('discount_value');
            }
            if (Schema::hasColumn('coupon_details', 'discount_type')) {
                $table->dropColumn('discount_type');
            }
            if (Schema::hasColumn('coupon_details', 'coupon_tier_id')) {
                $table->dropForeign(['coupon_tier_id']);
                $table->dropColumn('coupon_tier_id');
            }
        });

        Schema::dropIfExists('coupon_tiers');

        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'usage_limit_per_user')) {
                $table->dropColumn('usage_limit_per_user');
            }
            if (Schema::hasColumn('coupons', 'campaign_type')) {
                $table->dropColumn('campaign_type');
            }
            if (Schema::hasColumn('coupons', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
