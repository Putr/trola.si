<?php

namespace App\Http\Controllers;

use App\Services\StatsService;
use Carbon\Carbon;
use Illuminate\Routing\Controller as BaseController;

class StatsController extends BaseController
{
    private StatsService $statsService;

    public function __construct(StatsService $statsService)
    {
        $this->statsService = $statsService;
    }

    public function index()
    {
        $stats = [];
        $popularPages = $this->statsService->getPopularPages(7, 27);

        // Get stats for last 14 days - use aggregated data when available
        for ($i = 0; $i < 14; $i++) {
            $date = Carbon::now()->subDays($i)->format('Y_m_d');

            // Try to get aggregated data first (includes diff_from_avg and yoy)
            $aggregated = $this->statsService->getAggregatedDay($date);

            if ($aggregated !== null) {
                $stats[] = $aggregated;
            } else {
                // Fall back to basic daily stats if aggregation not available
                $stats[] = $this->statsService->getDailyStats($date);
            }
        }

        return view('stats', [
            'stats' => collect($stats),
            'popularPages' => $popularPages,
        ]);
    }
}
