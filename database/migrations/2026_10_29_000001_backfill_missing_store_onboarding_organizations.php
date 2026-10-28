<?php

use App\Models\User;
use App\Services\StoreOnboardingSync;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        User::where('role', 'organization')->whereDoesntHave('organization', fn ($q) => $q->withTrashed())
            ->chunkById(100, function ($stores) {
                foreach ($stores as $store) app(StoreOnboardingSync::class)->ensure($store);
            });
    }

    public function down(): void
    {
        // Keep restored records: they may already have reviews or uploaded documents.
    }
};
