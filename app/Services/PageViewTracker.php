<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Counts page views, skipping the automatic 60s reloads of station pages.
 */
class PageViewTracker
{
    public function __construct(
        private readonly StatsService $stats,
        private readonly Request $request,
    ) {}

    public function track(string $page): void
    {
        if ($this->request->cookie('autoreload')) {
            return;
        }

        $this->stats->trackPageView($page);
    }
}
