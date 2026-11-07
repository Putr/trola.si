<?php

namespace App\Services;

use App\Enums\FilterReason;
use App\Enums\IpClass;
use App\Services\Stats\BotDetector;
use App\Services\Stats\IpClassifier;
use Illuminate\Http\Request;

/**
 * Counts human page views. Reloads, self-announced bots, datacenter and non-European
 * addresses are counted under their filter reason instead, in that order.
 *
 * Privacy: the visitor IP is only classified in memory for this request. It is never
 * stored, hashed or logged.
 */
class PageViewTracker
{
    public function __construct(
        private readonly StatsService $stats,
        private readonly Request $request,
        private readonly BotDetector $bots,
        private readonly IpClassifier $ips,
    ) {}

    public function track(string $page): void
    {
        if ($this->request->cookie('autoreload')) {
            $this->stats->trackFiltered(FilterReason::Reload);

            return;
        }

        $userAgent = $this->request->userAgent();
        if ($this->bots->isBot($userAgent)) {
            $this->stats->trackFiltered(FilterReason::Bot);

            return;
        }

        $reason = match ($this->ips->classify((string) $this->request->ip())) {
            IpClass::Datacenter => FilterReason::Datacenter,
            IpClass::Foreign => FilterReason::Foreign,
            IpClass::Counted => null,
        };

        if ($reason !== null) {
            $this->stats->trackFiltered($reason);

            return;
        }

        $this->stats->trackPageView($page, $this->bots->isApp($userAgent) && $this->isHumanView($page));
    }

    /**
     * The app share is measured against human views, so only those pages count towards it.
     */
    private function isHumanView(string $page): bool
    {
        return in_array($page, ['home', 'search', 'geosearch'], true) || str_starts_with($page, 'station_');
    }
}
