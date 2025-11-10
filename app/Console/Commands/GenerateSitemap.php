<?php

namespace App\Console\Commands;

use App\Models\Station;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a sitemap.xml file including all stations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('Generating sitemap...');

            // Get all stations from the database
            // Note: XML sitemaps only include URLs, not page names (this is standard)
            $stations = Station::whereNotNull('code')->orderBy('code')->get();

            // Start XML content
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>https://trola.si/</loc>
        <lastmod>' . Carbon::now()->format('Y-m-d') . '</lastmod>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>https://trola.si/search</loc>
        <lastmod>' . Carbon::now()->format('Y-m-d') . '</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>';

            // Add each station with the "all" configuration
            foreach ($stations as $station) {
                $xml .= '
    <url>
        <loc>https://trola.si/' . $station->code . '/all</loc>
        <lastmod>' . Carbon::now()->format('Y-m-d') . '</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>';
            }

            // Close XML
            $xml .= '
</urlset>';

            // Save the sitemap.xml file
            $sitemapPath = public_path('sitemap.xml');
            File::put($sitemapPath, $xml);

            $this->info('Sitemap generated successfully with ' . $stations->count() . ' stations!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Error generating sitemap: ' . $e->getMessage());
            $this->error('Error generating sitemap: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
