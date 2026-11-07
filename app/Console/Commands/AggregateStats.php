<?php

namespace App\Console\Commands;

use App\Services\StatsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class AggregateStats extends Command
{
    protected $signature = 'stats:aggregate
        {--days=30 : Number of days to aggregate}
        {--force : Recalculate days that already have an aggregate}
        {--today-only : Only aggregate today}
        {--prune-empty : Delete aggregates of days that have no day hash}';

    protected $description = 'Aggregate daily page-view stats and recalculate the weekday rolling averages';

    public function handle(StatsService $stats): int
    {
        try {
            $stats->calculateRollingAverages(3);

            $today = Carbon::now();
            $days = $this->option('today-only') ? 1 : max(1, (int) $this->option('days'));
            $aggregated = 0;
            $pruned = 0;

            for ($i = 0; $i < $days; $i++) {
                $date = $today->copy()->subDays($i)->format('Y_m_d');

                // Today and yesterday (its last hour lands after the 23:00 run) are always
                // recalculated; older days only when missing or forced.
                if ($this->option('prune-empty') && $stats->pruneEmptyDay($date)) {
                    $pruned++;
                }

                if ($i <= 1 || $this->option('force') || $stats->getAggregatedDay($date) === null) {
                    $stats->aggregateDay($date, forceRecalculate: true);
                    $aggregated++;
                }
            }
        } catch (Throwable $e) {
            report($e);
            $this->error('Stats aggregation failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Aggregated {$aggregated} of {$days} days.".($this->option('prune-empty') ? " Pruned {$pruned} empty aggregates." : ''));

        return self::SUCCESS;
    }
}
