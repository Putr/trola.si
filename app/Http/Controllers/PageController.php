<?php

namespace App\Http\Controllers;

use App\Services\PageViewTracker;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function __construct(private readonly PageViewTracker $tracker) {}

    /**
     * The home page: a search box that immediately looks for nearby stations.
     */
    public function index(): View
    {
        $this->tracker->track('home');

        return view('search', [
            'stations' => collect(),
            'query' => '',
            'direction' => 'all',
            'directionToCenter' => null,
            'hrefs' => [
                'to' => route('search', ['direction' => 'to'], false),
                'from' => route('search', ['direction' => 'from'], false),
                'all' => route('search', ['direction' => 'all'], false),
            ],
            'useLocation' => true,
            'lookingForLocation' => true,
        ]);
    }

    public function help(): View
    {
        $this->tracker->track('help');

        return view('help');
    }
}
