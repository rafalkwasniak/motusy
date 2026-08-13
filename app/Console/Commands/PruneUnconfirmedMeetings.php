<?php

namespace App\Console\Commands;

use App\Services\MeetingService;
use Illuminate\Console\Command;

class PruneUnconfirmedMeetings extends Command
{
    protected $signature = 'meetings:prune';

    protected $description = 'Remove detection reports the other side never matched';

    public function handle(MeetingService $meetings): int
    {
        $removed = $meetings->pruneUnconfirmed();

        $this->info("Removed {$removed} unconfirmed detection(s).");

        return self::SUCCESS;
    }
}
