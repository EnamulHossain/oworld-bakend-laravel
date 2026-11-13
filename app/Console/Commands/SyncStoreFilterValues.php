<?php

namespace App\Console\Commands;

use App\Services\StoreEventFilterSync;
use Illuminate\Console\Command;

class SyncStoreFilterValues extends Command
{
    protected $signature = 'filters:sync-stores';

    protected $description = 'Fill missing or empty matching store filter selections without overwriting existing choices';

    public function handle(StoreEventFilterSync $sync): int
    {
        $count = $sync->repairStores();
        $this->info("Repaired matching filter selections for {$count} stores.");
        return self::SUCCESS;
    }
}
