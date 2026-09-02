<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->json('branch_ids')->nullable()->after('organization_id');
            $table->string('branch_assignment_status', 20)->default('approved')->after('branch_ids');
            $table->foreignId('branch_requested_by')->nullable()->after('branch_assignment_status')->constrained('users')->nullOnDelete();
            $table->foreignId('branch_approved_by')->nullable()->after('branch_requested_by')->constrained('users')->nullOnDelete();
            $table->timestamp('branch_approved_at')->nullable()->after('branch_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_approved_by');
            $table->dropConstrainedForeignId('branch_requested_by');
            $table->dropColumn(['branch_ids', 'branch_assignment_status', 'branch_approved_at']);
        });
    }
};
