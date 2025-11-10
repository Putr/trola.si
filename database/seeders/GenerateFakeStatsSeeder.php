<?php

namespace Database\Seeders;

use App\Models\Station;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Redis;

class GenerateFakeStatsSeeder extends Seeder
{
    /**
     * Generate fake stats for the last month
     */
    public function run(): void
    {
        // Ask user if they want to reset all existing stats
        if ($this->command->confirm('Do you want to reset all existing stats?', false)) {
            $this->command->info('Resetting all existing stats...');
            $this->resetAllStats();
            $this->command->info('✓ All existing stats have been reset');
        }

        $this->command->info('Generating fake stats for the last 30 days...');

        // Get all stations from database
        $stations = Station::whereNotNull('code')->get();

        if ($stations->isEmpty()) {
            $this->command->warn('No stations found in database. Please run LoadStations seeder first.');
            return;
        }

        $this->command->info("Found {$stations->count()} stations");

        // Generate stats for the last 30 days
        $days = 30;
        $generatedKeys = 0;

        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($i);
            $dateKey = $date->format('Y_m_d');

            // Generate stats for standard pages
            $this->setPageViews($dateKey, 'home', rand(100, 500));
            $this->setPageViews($dateKey, 'search', rand(50, 200));
            $this->setPageViews($dateKey, 'geosearch', rand(20, 100));
            $this->setPageViews($dateKey, 'not_found', rand(5, 30));
            $this->setPageViews($dateKey, 'help', rand(2, 15));
            $generatedKeys += 5;

            // Generate stats for station pages
            // Not every station gets views every day - more realistic distribution
            foreach ($stations as $station) {
                $code = $station->code;

                // Only 30% of stations get views on any given day
                if (rand(1, 100) > 30) {
                    continue;
                }

                // Regular station views - vary by popularity
                // Make some stations more popular (15% chance of being popular)
                $isPopular = rand(1, 100) <= 15;
                $stationViews = $isPopular ? rand(20, 80) : rand(1, 15);

                $this->setPageViews($dateKey, "station_{$code}", $stationViews);
                $generatedKeys++;

                // "Both directions" views - typically 30-50% of regular views
                // Only generate if there are regular views
                if ($stationViews > 0) {
                    $bothDirectionsViews = (int)($stationViews * (rand(30, 50) / 100));
                    if ($bothDirectionsViews > 0) {
                        $this->setPageViews($dateKey, "station_{$code}_all", $bothDirectionsViews);
                        $generatedKeys++;
                    }
                }
            }

            // Show progress every 5 days
            if (($i + 1) % 5 === 0) {
                $this->command->info("Generated stats for " . ($i + 1) . " days...");
            }
        }

        $this->command->info("✓ Generated fake stats for {$days} days");
        $this->command->info("✓ Created {$generatedKeys} stat keys");
    }

    /**
     * Reset all existing stats by deleting all stats keys
     *
     * @return void
     */
    private function resetAllStats(): void
    {
        try {
            // Get all stats keys
            $pattern = 'stats_*';
            $keys = Redis::keys($pattern);

            if (empty($keys)) {
                $this->command->info('No existing stats found to reset.');
                return;
            }

            $this->command->info("Found " . count($keys) . " stat keys to delete...");

            // Delete all stats keys
            foreach ($keys as $key) {
                // Remove prefix if present (Laravel Redis returns keys with prefix)
                $cleanKey = $this->removePrefix($key);
                Redis::del($cleanKey);
            }

            $this->command->info("Deleted " . count($keys) . " stat keys");
        } catch (\Exception $e) {
            $this->command->error('Failed to reset stats: ' . $e->getMessage());
        }
    }

    /**
     * Remove Redis prefix from a key if present
     *
     * @param string $key
     * @return string
     */
    private function removePrefix(string $key): string
    {
        // Get the configured prefix from Redis options
        $prefix = config('database.redis.options.prefix', '');

        // Remove prefix if it exists at the start of the key
        if ($prefix && strpos($key, $prefix) === 0) {
            return substr($key, strlen($prefix));
        }

        return $key;
    }

    /**
     * Set page views for a specific date and page
     *
     * @param string $dateKey Date in Y_m_d format
     * @param string $page Page identifier
     * @param int $views Number of views
     * @return void
     */
    private function setPageViews(string $dateKey, string $page, int $views): void
    {
        $key = "stats_{$dateKey}_{$page}";
        Redis::set($key, $views);
    }
}
