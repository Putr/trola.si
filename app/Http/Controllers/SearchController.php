<?php

namespace App\Http\Controllers;

use App\Enums\Direction;
use App\Http\Requests\GeosearchRequest;
use App\Models\Station;
use App\Services\LppApiService;
use App\Services\PageViewTracker;
use App\Support\FulltextQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const MAX_RESULTS = 10;

    public function __construct(private readonly PageViewTracker $tracker) {}

    /**
     * Search stations by name or code. A single hit goes straight to the station.
     */
    public function search(Request $request): View|RedirectResponse
    {
        $this->tracker->track('search');

        $query = trim($request->string('q')->toString());
        $direction = Direction::fromInput($request->query('direction'));

        $stations = $this->searchStations($query, $direction);

        if ($query !== '' && $stations->count() === 1) {
            return $this->redirectToStation($stations->first(), $direction);
        }

        return view('search', [
            'stations' => $stations,
            'query' => $query,
            'direction' => $direction->value,
            'directionToCenter' => $direction->toCenter(),
            'useLocation' => false,
            'hrefs' => $this->directionLinks('search', ['q' => $query]),
            'locationFailed' => $request->has('locationFailed'),
        ]);
    }

    /**
     * Stations near the visitor, nearest first, as reported by LPP.
     */
    public function geosearch(GeosearchRequest $request, LppApiService $api): View|RedirectResponse
    {
        $this->tracker->track('geosearch');

        $latitude = $request->latitude();
        $longitude = $request->longitude();
        $direction = Direction::fromInput($request->query('direction'));
        $coordinates = ['lat' => $latitude, 'lon' => $longitude];

        $nearby = collect($api->getStationsInRange($latitude, $longitude) ?? []);
        $stationsByCode = Station::query()
            ->whereIn('code', $nearby->pluck('ref_id')->all())
            ->when($direction->toCenter() !== null, fn (Builder $q) => $q->where('is_direction_to_center', $direction->toCenter()))
            ->get()
            ->keyBy('code');

        $stations = $nearby
            ->filter(fn (array $item): bool => $stationsByCode->has($item['ref_id']))
            ->map(function (array $item) use ($stationsByCode): Station {
                $station = $stationsByCode[$item['ref_id']];
                $station->setAttribute('distance', (int) round($item['distance']));

                return $station;
            })
            ->values();

        if ($stations->count() === 1) {
            return $this->redirectToStation($stations->first(), Direction::All);
        }

        return view('search', [
            'stations' => $stations,
            'query' => '',
            'direction' => $direction->value,
            'directionToCenter' => $direction->toCenter(),
            'hrefs' => $this->directionLinks('geosearch', $coordinates),
            'useLocation' => true,
            'error' => $stations->isEmpty() ? 'V bližini ni najdenih postaj.' : null,
        ]);
    }

    /**
     * @return Collection<int, Station>
     */
    private function searchStations(string $query, Direction $direction): Collection
    {
        $builder = Station::query()
            ->when($direction->toCenter() !== null, fn (Builder $q) => $q->where('is_direction_to_center', $direction->toCenter()))
            ->limit(self::MAX_RESULTS);

        if ($query === '') {
            return $builder->get();
        }

        $like = FulltextQuery::escapeLike($query);
        $fulltext = FulltextQuery::booleanPrefix($query);

        $builder->where(function (Builder $q) use ($query, $like, $fulltext): void {
            $q->where('name', $query)
                ->orWhere('code', 'like', $like.'%')
                ->orWhere('name', 'like', $like.'%')
                ->orWhere('name', 'like', '%'.$like.'%');

            if ($fulltext !== null) {
                $q->orWhereRaw('MATCH(name) AGAINST(? IN BOOLEAN MODE)', [$fulltext]);
            }
        });

        // Rank: exact name, code prefix, name prefix, full-text, substring.
        $ranking = 'CASE WHEN name = ? THEN 1 WHEN code LIKE ? THEN 2 WHEN name LIKE ? THEN 3 ';
        $bindings = [$query, $like.'%', $like.'%'];
        if ($fulltext !== null) {
            $ranking .= 'WHEN MATCH(name) AGAINST(? IN BOOLEAN MODE) THEN 4 ';
            $bindings[] = $fulltext;
        }
        $builder->orderByRaw($ranking.'ELSE 5 END', $bindings);

        return $builder->get();
    }

    private function redirectToStation(Station $station, Direction $direction): RedirectResponse
    {
        return $direction === Direction::All
            ? redirect()->route('station.both-directions', ['code' => $station->code])
            : redirect()->route('station', ['code' => $station->code]);
    }

    /**
     * @param  array<string, scalar>  $params
     * @return array{to: string, from: string, all: string}
     */
    private function directionLinks(string $route, array $params): array
    {
        return [
            'to' => route($route, [...$params, 'direction' => 'to'], false),
            'from' => route($route, [...$params, 'direction' => 'from'], false),
            'all' => route($route, [...$params, 'direction' => 'all'], false),
        ];
    }
}
