<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * One-off migration of the legacy per-page counters `stats_{Y_m_d}_{page}` into one hash per
 * day, `stats_day_{Y_m_d}` (field = page id, value = views).
 *
 * Without `--execute` it only reports what it would fold.
 *
 * Idempotency and concurrency: every legacy key is folded by a single Lua script, which Redis
 * runs atomically. It reads the legacy value, HINCRBYs the day hash by it and DELetes the legacy
 * key in one step. Because the add is an increment (never an overwrite), live writes that
 * already reached the hash, or land at any moment during the migration, are preserved: the
 * result is always live + legacy. Because the legacy key is deleted in the same atomic step, a
 * key can be folded at most once, so re-running (or two runs overlapping) never double-counts.
 * If an old, not yet restarted worker still INCRs a legacy key after it was folded, the key is
 * recreated and the next run folds that remainder. Legacy keys are never deleted in bulk.
 *
 * Only keys of the exact shape `stats_dddd_dd_dd_{page}` are touched, so `stats_agg_day_*`,
 * `stats_avg_dow_*` and the new `stats_day_*` hashes are never matched.
 */
class MigrateStatsToHashes extends Command
{
    private const FOLD_SCRIPT = <<<'LUA'
local value = redis.call('GET', KEYS[1])
if not value then
    return 0
end
redis.call('HINCRBY', KEYS[2], ARGV[1], value)
redis.call('DEL', KEYS[1])
return tonumber(value)
LUA;

    private const SCAN_BATCH = 500;

    protected $signature = 'stats:migrate-to-hashes
        {--execute : Actually fold the legacy keys; without it only a report is printed}';

    protected $description = 'Fold legacy per-page stats counters into one Redis hash per day';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $prefix = (string) config('database.redis.options.prefix', '');
        $keys = 0;
        $views = 0;
        $dates = [];
        $errors = 0;
        $cursor = 0;

        $this->info($execute ? 'Folding legacy stats keys into daily hashes...' : 'Dry run: nothing will be changed (use --execute to fold).');

        try {
            do {
                // Predis prefixes command keys but not SCAN's MATCH pattern, so add it here.
                $result = Redis::scan($cursor, ['match' => $prefix.'stats_????_??_??_*', 'count' => self::SCAN_BATCH]);
                if (! is_array($result)) {
                    break;
                }

                [$cursor, $batch] = $result;

                foreach (array_unique($batch) as $fullKey) {
                    $key = $prefix !== '' && str_starts_with($fullKey, $prefix) ? substr($fullKey, strlen($prefix)) : $fullKey;

                    if (! preg_match('/^stats_(\d{4}_\d{2}_\d{2})_(.+)$/', $key, $matches)) {
                        continue;
                    }

                    try {
                        $moved = $execute
                            ? (int) Redis::command('eval', [self::FOLD_SCRIPT, 2, $key, "stats_day_{$matches[1]}", $matches[2]])
                            : (int) Redis::get($key);
                    } catch (Throwable $e) {
                        $errors++;
                        $this->error("Failed to process {$key}: ".$e->getMessage());

                        continue;
                    }

                    $keys++;
                    $views += $moved;
                    $dates[$matches[1]] = true;

                    if ($keys % 5000 === 0) {
                        $this->line("  ...{$keys} keys");
                    }
                }
            } while ((string) $cursor !== '0');
        } catch (Throwable $e) {
            report($e);
            $this->error('Migration failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $verb = $execute ? 'folded' : 'would fold';
        $this->info("Keys {$verb}: {$keys}; dates: ".count($dates)."; total views: {$views}.");

        if ($keys === 0) {
            $this->warn("No legacy keys found. On a first run that means a wrong Redis prefix (now '{$prefix}').");
        }

        if ($errors > 0) {
            $this->error("{$errors} keys failed; re-run to retry them.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
