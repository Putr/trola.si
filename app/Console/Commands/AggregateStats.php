<?php

namespace App\Console\Commands;

use App\Services\StatsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AggregateStats extends Command
{
    protected $signature = 'stats:aggregate {--days=30 : Number of days to aggregate} {--force : Force recalculation of existing aggregates} {--today-only : Only aggregate today\'s data}';

    protected $description = 'Aggregate stats data by day, week, and month, and calculate rolling averages and year-over-year comparisons';

    private StatsService $statsService;

    public function __construct(StatsService $statsService)
    {
        parent::__construct();
        $this->statsService = $statsService;
    }

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $force = $this->option('force');
        $todayOnly = $this->option('today-only');

        if ($todayOnly) {
            // Lightweight mode: only aggregate today's data
            $this->info('Aggregating today\'s stats only...');
            $todayKey = Carbon::now()->format('Y_m_d');

            // Recalculate rolling averages (needed for PRA calculation)
            $this->statsService->calculateRollingAverages(3);

            // Always force recalculation for today
            $this->statsService->aggregateDay($todayKey, true);
            $this->info('✓ Today\'s stats aggregated');

            return Command::SUCCESS;
        }

        $this->info("Starting stats aggregation for the last {$days} days...");

        // Calculate rolling averages first (needed for daily aggregates)
        $this->info('Calculating rolling averages...');
        $this->statsService->calculateRollingAverages(3);
        $this->info('✓ Rolling averages calculated');

        // Aggregate days
        $this->info('Aggregating daily stats...');
        $aggregatedDays = 0;
        $todayKey = Carbon::now()->format('Y_m_d');

        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($i);
            $dateKey = $date->format('Y_m_d');

            // Always re-aggregate "today" to keep it up-to-date throughout the day
            // For other days, check cache unless force is used
            $shouldAggregate = false;
            if ($dateKey === $todayKey) {
                // Always aggregate today (even if cached) to show current progress
                $shouldAggregate = true;
            } elseif ($force || $this->statsService->getAggregatedDay($dateKey) === null) {
                // For completed days, only aggregate if not cached (or force)
                $shouldAggregate = true;
            }

            if ($shouldAggregate) {
                // Force recalculation for "today" to ensure fresh data
                $forceToday = ($dateKey === $todayKey);
                $this->statsService->aggregateDay($dateKey, $forceToday);
                $aggregatedDays++;
            }

            if (($i + 1) % 10 === 0) {
                $this->info('Processed ' . ($i + 1) . ' days...');
            }
        }
        $this->info("✓ Aggregated {$aggregatedDays} days");

        // Aggregate weeks
        $this->info('Aggregating weekly stats...');
        $this->aggregateWeeks($days, $force);
        $this->info('✓ Weekly stats aggregated');

        // Aggregate months
        $this->info('Aggregating monthly stats...');
        $this->aggregateMonths($days, $force);
        $this->info('✓ Monthly stats aggregated');

        $this->info('Stats aggregation completed successfully!');

        return Command::SUCCESS;
    }

    /**
     * Aggregate weeks for the period
     *
     * @param  int  $days  Number of days to look back
     * @param  bool  $force  Force recalculation
     */
    private function aggregateWeeks(int $days, bool $force): void
    {
        $startDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();

        $currentWeek = $startDate->copy()->startOfWeek();
        $weeksProcessed = 0;

        while ($currentWeek->lte($endDate)) {
            $year = $currentWeek->format('Y');
            $week = $currentWeek->week;

            // Check if already aggregated (unless force)
            if (! $force && $this->statsService->getAggregatedWeek($year, $week) !== null) {
                $currentWeek->addWeek();

                continue;
            }

            $this->statsService->aggregateWeek($year, $week);
            $weeksProcessed++;
            $currentWeek->addWeek();
        }

        $this->info("  Processed {$weeksProcessed} weeks");
    }

    /**
     * Aggregate months for the period
     *
     * @param  int  $days  Number of days to look back
     * @param  bool  $force  Force recalculation
     */
    private function aggregateMonths(int $days, bool $force): void
    {
        $startDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();

        $currentMonth = $startDate->copy()->startOfMonth();
        $monthsProcessed = 0;

        while ($currentMonth->lte($endDate)) {
            $year = $currentMonth->format('Y');
            $month = (int) $currentMonth->format('n');

            // Check if already aggregated (unless force)
            if (! $force && $this->statsService->getAggregatedMonth($year, $month) !== null) {
                $currentMonth->addMonth()->startOfMonth();

                continue;
            }

            $this->statsService->aggregateMonth($year, $month);
            $monthsProcessed++;
            $currentMonth->addMonth()->startOfMonth();
        }

        $this->info("  Processed {$monthsProcessed} months");
    }
}
