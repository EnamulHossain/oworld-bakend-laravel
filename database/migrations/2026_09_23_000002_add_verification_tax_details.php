<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organization_verifications', function (Blueprint $table) {
            $table->date('established_date')->nullable();
            $table->string('bin_vat_no', 100)->nullable();
            $table->string('tin_no', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organization_verifications', function (Blueprint $table) {
            $table->dropColumn(['established_date', 'bin_vat_no', 'tin_no']);
        });
    }
};
