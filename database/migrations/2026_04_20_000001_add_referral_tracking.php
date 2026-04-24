<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code', 32)->nullable()->unique()->after('avatar');
            }
            if (!Schema::hasColumn('users', 'referred_by_user_id')) {
                $table->foreignId('referred_by_user_id')->nullable()->after('referral_code')->constrained('users')->nullOnDelete();
            }
        });

        if (!Schema::hasTable('referrals')) {
            Schema::create('referrals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('referred_user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('referral_code', 32);
                $table->enum('status', ['completed'])->default('completed');
                $table->dateTime('completed_at')->nullable();
                $table->timestamps();

                $table->index(['referrer_user_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'referred_by_user_id')) {
                $table->dropForeign(['referred_by_user_id']);
                $table->dropColumn('referred_by_user_id');
            }
            if (Schema::hasColumn('users', 'referral_code')) {
                $table->dropUnique(['referral_code']);
                $table->dropColumn('referral_code');
            }
        });
    }
};
