<?php

namespace App\Console\Commands;

use App\Services\BleIdentityService;
use Illuminate\Console\Command;

class PruneRetiredBleIdentities extends Command
{
    protected $signature = 'ble:prune-identities';

    protected $description = 'Remove retired BLE tokens no late report could name any more';

    public function handle(BleIdentityService $identities): int
    {
        $removed = $identities->pruneRetired();

        $this->info("Removed {$removed} retired token(s).");

        return self::SUCCESS;
    }
}
