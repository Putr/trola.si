<?php

namespace App\Http\Controllers;

use App\Services\StatsService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

class StatsController extends Controller
{
    private const DAYS_SHOWN = 14;

    private const CACHE_SECONDS = 600;

    public function index(StatsService $stats): View
    {
        return view('stats', Cache::remember('stats_page', self::CACHE_SECONDS, function () use ($stats): array {
            $days = collect(range(0, self::DAYS_SHOWN - 1))->map(function (int $daysAgo) use ($stats): array {
                $date = Carbon::now()->subDays($daysAgo)->format('Y_m_d');

                return $stats->getAggregatedDay($date) ?? $stats->getDailyStats($date);
            });

            return [
                'stats' => $days,
                'popularPages' => $stats->getPopularPages(7, 27),
            ];
        }));
    }
}
