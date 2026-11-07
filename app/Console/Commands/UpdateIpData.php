<?php

namespace App\Console\Commands;

use App\Actions\CompileIpRanges;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class UpdateIpData extends Command
{
    protected $signature = 'stats:update-ip-data';

    protected $description = 'Download the iptoasn data and compile the IP range lookup used to filter stats';

    public function handle(CompileIpRanges $compiler): int
    {
        $target = (string) config('stats.ip_data_path');
        $directory = dirname($target);
        $work = $directory.'/.update-'.bin2hex(random_bytes(4));
        $files = ["{$work}.tsv.gz", "{$work}.tsv", "{$work}.php"];

        try {
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException("Cannot create {$directory}");
            }

            Http::timeout(120)->sink($files[0])->get((string) config('stats.ip_data_url'))->throw();
            $this->gunzip($files[0], $files[1]);
            $counts = $compiler->fromTsv($files[1], $files[2]);

            $minV4 = (int) config('stats.ip_data_min_v4', 100000);
            $minV6 = (int) config('stats.ip_data_min_v6', 20000);
            if ($counts['v4'] < $minV4 || $counts['v6'] < $minV6) {
                throw new RuntimeException("Downloaded IP data looks truncated ({$counts['v4']} IPv4, {$counts['v6']} IPv6 ranges; expected at least {$minV4} and {$minV6})");
            }

            if (! rename($files[2], $target)) {
                throw new RuntimeException("Cannot install {$target}");
            }
        } catch (Throwable $e) {
            report($e);
            $this->error('IP data update failed, keeping the previous data: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($target, true);
        }

        $this->info("IP data updated: {$counts['v4']} IPv4 and {$counts['v6']} IPv6 ranges.");

        return self::SUCCESS;
    }

    private function gunzip(string $source, string $destination): void
    {
        $in = gzopen($source, 'rb');
        $out = fopen($destination, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException('Cannot unpack the downloaded IP data');
        }

        try {
            while (! gzeof($in)) {
                $chunk = gzread($in, 1 << 20);
                if ($chunk === false) {
                    throw new RuntimeException('Downloaded IP data is not valid gzip');
                }
                fwrite($out, $chunk);
            }
        } finally {
            gzclose($in);
            fclose($out);
        }
    }
}
