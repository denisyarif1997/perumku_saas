<?php

namespace App\Console\Commands;

use App\Services\Billing\SubscriptionService;
use Illuminate\Console\Command;

/** Scheduler harian: kunci estate yang trial/temponya lewat grace period. */
class SweepSubscriptions extends Command
{
    protected $signature = 'subscription:sweep';

    protected $description = 'Suspend perumahan yang trial/langganannya kedaluwarsa';

    public function handle(SubscriptionService $service): int
    {
        $suspended = $service->sweepExpired();

        $this->info("Sweep selesai: {$suspended} perumahan di-suspend.");

        return self::SUCCESS;
    }
}
