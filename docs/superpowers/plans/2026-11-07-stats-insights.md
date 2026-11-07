# Stats Insights Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Count only human page views and show a `/stats` page that answers "is it used, is use growing, and which stations are used and how is that changing".

**Architecture:**
- The tracker classifies each request in memory, in this order: reload, bot User-Agent, IP class (datacenter/foreign via a compiled iptoasn lookup). It then increments either the page field or a `filtered:{reason}` field in the existing per-day hash; app requests also increment `app`.
- Daily aggregates gain `app` and `filtered`.
- A new `StatsInsights` service builds the page data. Totals and the trend come from aggregates; station tables and lookups come from pipelined reads of the day hashes, by key.
- The page is server-rendered with inline SVG and no JS.

**Tech Stack:** Laravel 13, PHP 8.4, Redis (predis 3), Pest 4, Blade + Tailwind 3.

**Spec:** `docs/superpowers/specs/2026-11-07-stats-insights-design.md` (read the "Calls made after the builder check" section; it is binding). Mockup: `docs/superpowers/specs/2026-11-07-stats-insights-mockup.html`.

## Global Constraints

- **No new Composer/npm dependencies.** No JS on `/stats`. No external scripts.
- **Never store, log or hash IP addresses.** No cookies other than the existing `autoreload`.
- **Slovenian user-facing copy**, English code/comments/commits. Commits carry no AI attribution trailers.
- **Existing Redis keys keep working:** `stats_day_{Y_m_d}`, `stats_agg_day_{Y_m_d}`, `stats_avg_dow_{n}`. Aggregates without the new fields must still render.
- **Prefix awareness:** predis does not prefix SCAN MATCH. Don't SCAN at all.
- **Tests:**
  - Tests never hit the network (`Http::fake()`).
  - Tests that run the Redis prefix use `useRedisPrefix()` from `tests/Feature/StatsServiceTest.php`. Move it to `tests/Pest.php` if another file needs it.
- **Tooling:**
  - TDD for the filter, the IP lookup and all aggregation maths.
  - Run `vendor/bin/pint --dirty` and `composer stan` (level 5) before each commit.
  - Test DB/Redis: `composer test:up`; run with `php artisan test --compact`.
- **No coloured left-border accent stripes** anywhere. Show +/− with small tinted pill badges.
- **The page must render in old browsers:** Tailwind 3 and the pinned Vite target stay as they are. Plain HTML/SVG only.

## Review Focus

1. A spoofed `X-Forwarded-For: 8.8.8.8` sent from the internet must not change the visitor IP; only trusted hops are stripped. Test in Task 1.
2. An ordinary Android WebView UA (`... wv) ... Chrome/... Mobile Safari/537.36 TrolaAndroidApp`) must count, not be classed as a bot. Test in Task 1.
3. A missing or corrupt compiled IP file must mean "count everything", never an exception on a public page. Test in Task 2.
4. Old aggregates without `app`/`filtered`, and missing days (null aggregate), must render as gaps or "ni podatkov", not as zeros or errors. Test in Task 4.
5. `?obdobje=abc`, `?seznam[]=x` and `?postaja=` with an unknown name must return 200 with defaults or a "postaja ni najdena" message, never a 500. Test in Task 5.

---

### Task 1: Config, trusted proxies, bot detection

**Files:**
- Create: `config/stats.php`, `app/Services/Stats/BotDetector.php`, `tests/Unit/BotDetectorTest.php`
- Modify: `bootstrap/app.php` (trust proxies), `tests/Feature/PagesTest.php` (proxy test) or a new `tests/Feature/TrustedProxyTest.php`

**Interfaces:**
- Produces:
  - `config('stats.filters_live_since')`: string `Y-m-d`, from env `STATS_FILTERS_SINCE`, default `'2026-11-07'`.
  - `config('stats.europe')`: list of country codes, exactly the list in spec call 10.
  - `config('stats.datacenter_keywords')`: list of case-insensitive substrings.
  - `config('stats.cdn_exclusions')`: `['cloudflare', 'akamai', 'fastly']`.
  - `config('stats.bot_patterns')`: list of case-insensitive substrings.
  - `config('stats.ip_data_url')`: `'https://iptoasn.com/data/ip2asn-combined.tsv.gz'`.
  - `config('stats.ip_data_path')`: `storage_path('app/ipdata/ranges.php')`.
  - `BotDetector::isBot(?string $userAgent): bool`
  - `BotDetector::isApp(?string $userAgent): bool` (true when the UA contains `TrolaAndroidApp`)

- [ ] **Step 1: Write failing tests**
  - `BotDetectorTest`, using datasets:
    - `isBot` is true for: `null`, `''`, `Googlebot/2.1`, `Mozilla/5.0 (compatible; bingbot/2.0)`, `facebookexternalhit/1.1`, `WhatsApp/2.23`, `curl/8.4.0`, `python-requests/2.31`, `Go-http-client/1.1`, `HeadlessChrome/120`, `UptimeRobot/2.0`, `Slackbot-LinkExpanding`.
    - `isBot` is false for a real iPhone Safari UA, a desktop Chrome UA, and the Android WebView UA `Mozilla/5.0 (Linux; Android 14; Pixel 7 Build/UQ1A; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/129.0.0.0 Mobile Safari/537.36 TrolaAndroidApp`.
    - `isApp` is true only for that last UA.
  - Proxy feature test:
    - A route that echoes `request()->ip()` is not allowed. Instead assert through `Request::create` + `app(\Illuminate\Http\Middleware\TrustProxies::class)->handle()`, or call any page and capture via a test-only route registered in the test with `Route::get('/_ip', fn () => request()->ip())`.
    - With `REMOTE_ADDR=172.18.0.1` and `X-Forwarded-For: 8.8.8.8, 93.103.1.1`, `ip()` is `93.103.1.1`.
    - With `REMOTE_ADDR=93.103.1.1` (untrusted) and `X-Forwarded-For: 8.8.8.8`, `ip()` is `93.103.1.1`.
- [ ] **Step 2: Run.** `php artisan test --compact --filter='BotDetector|TrustedProxy'` → FAIL (class missing, ip mismatch).
- [ ] **Step 3: Implement.**
  - Write `config/stats.php` with the values above.
  - Bot patterns are at least: `bot`, `crawl`, `spider`, `slurp`, `facebookexternalhit`, `whatsapp`, `telegram`, `discord`, `preview`, `curl`, `wget`, `python`, `go-http-client`, `java/`, `okhttp`, `libwww`, `httpclient`, `axios`, `node-fetch`, `headless`, `phantomjs`, `uptime`, `monitor`, `pingdom`, `lighthouse`, `scrapy`.
  - Datacenter keywords: `host`, `cloud`, `server`, `data center`, `datacenter`, `data-center`, `vps`, `colo`, `amazon`, `google`, `microsoft`, `digitalocean`, `linode`, `ovh`, `hetzner`, `contabo`, `scaleway`, `leaseweb`, `vultr`, `choopa`, `oracle`, `alibaba`, `tencent`, `m247`, `datacamp`, `cdn77`, `ionos`, `netcup`, `upcloud`, `hostinger`, `godaddy`, `rackspace`, `softlayer`, `selectel`, `timeweb`, `psychz`, `zenlayer`, `quadranet`, `frantech`.
  - In `bootstrap/app.php`, add `$middleware->trustProxies(at: ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '::1', 'fc00::/7'], headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);`
- [ ] **Step 4: Run.** Same filter → PASS. Then the full suite → all pass.
- [ ] **Step 5: Commit.** `feat: trust local proxies and detect self-announced bots`

### Task 2: IP data compiler and classifier

**Files:**
- Create:
  - `app/Enums/IpClass.php`
  - `app/Services/Stats/IpClassifier.php`
  - `app/Actions/CompileIpRanges.php`
  - `app/Console/Commands/UpdateIpData.php` (`stats:update-ip-data`)
  - `tests/Feature/IpDataTest.php`
  - `tests/fixtures/ip2asn-sample.tsv`
- Modify: `routes/console.php` (monthly: `->monthlyOn(1, '04:00')->withoutOverlapping()`), `.gitignore` (`/storage/app/ipdata`), `CLAUDE.md` (one line in "How it works": dataset, licence PDDL v1.0, refresh schedule)

**Interfaces:**
- Consumes: the `config('stats.*')` keys from Task 1.
- Produces:
  - `enum IpClass: string { case Counted = 'counted'; case Datacenter = 'datacenter'; case Foreign = 'foreign'; }`
  - `CompileIpRanges::fromTsv(string $tsvPath, string $outputPath): array{v4: int, v6: int}` (the number of merged ranges written)
  - `IpClassifier::classify(string $ip): IpClass`. Bind it as a singleton so the data file is included once per process.
  - `stats:update-ip-data`:
    - Downloads `config('stats.ip_data_url')` with `Http::timeout(120)->sink(...)` to a temp `.gz` file, gunzips it, and compiles to a temp path.
    - Atomically renames the result over `ip_data_path`, and returns SUCCESS with the counts printed.
    - On any failure, keeps the old file, `report()`s, and returns FAILURE.

- [ ] **Step 1: Write the fixture and failing tests.**
  - The fixture has about 10 TSV lines (`start\tend\tasn\tcc\tdesc`) covering:
    - an SI ISP (`93.103.0.0–93.103.255.255`, `SIOL-NET Telekom Slovenije, d.d.`) → Counted
    - a DE Hetzner range → Datacenter
    - a US ISP range (`Comcast`) → Foreign
    - a US Cloudflare range → **Foreign**, not Datacenter: CDN exclusion stops it counting as a datacenter, and the country rule then applies
    - an `0\tNone\tNot routed` range → Counted
    - an IPv6 SI range (`2a00:ee0::–2a00:ee0:ffff:ffff:ffff:ffff:ffff:ffff`) → Counted
    - an IPv6 AWS US range → Datacenter
    - two adjacent SI ranges, so the merge shows

    Rulings: classification order is datacenter keyword first (unless the description matches a CDN exclusion), then not-in-Europe → Foreign, else Counted. ASN 0 → Counted.
  - Tests:
    - `it('classifies addresses from the compiled data')`: compile the fixture to a temp path, point `stats.ip_data_path` at it, rebuild the singleton, and assert each class above. Also: an address in no range (e.g. `10.0.0.5`) → Counted; an invalid string `'nope'` → Counted.
    - `it('merges adjacent ranges of the same class')`: the returned v4 count is less than the number of v4 fixture lines.
    - `it('counts everything when the data file is missing or corrupt')`: missing path → Counted; a file containing `<?php return 'garbage';` → Counted, no exception.
    - `it('keeps the previous data when the download fails')`: `Http::fake([... => Http::response('', 500)])`, an existing file stays byte-identical, and the command fails.
    - `it('downloads and installs fresh data')`: `Http::fake` returns `gzencode(fixture)`; the command succeeds and the file classifies correctly.
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/IpDataTest.php` → FAIL (classes missing).
- [ ] **Step 3: Implement.** The packed format, which is this task's one real algorithm:
  - Per family, merge consecutive ranges with the same class.
  - Fill gaps implicitly: an entry is `(start, class)`, and a gap before the next listed start becomes an entry with class Counted at `previous end + 1`.
  - Pack each start as `inet_pton()` bytes (4 or 16, big-endian) followed by 1 class byte (`0` Counted, `1` Datacenter, `2` Foreign).
  - The output PHP file is `<?php return ['v4' => '<bytes>', 'v6' => '<bytes>'];`. Single-quoted literals escape `\` and `'` only, so opcache holds them as interned strings.
  - Lookup is a binary search over fixed-width records: compare `substr($data, $i * $w, $w - 1)` with `inet_pton($ip)` using `strcmp`, and find the last start ≤ ip.
  - Validate on load: string type and length divisible by the record width. Otherwise use empty data.
  - Stream the TSV line by line; do not hold 719k rows.
- [ ] **Step 4: Run** the same file → PASS, then the full suite.
- [ ] **Step 5: Commit.** `feat: classify visitor IPs from compiled iptoasn data`

### Task 3: Filtered tracking and aggregate fields

**Files:**
- Create: `app/Enums/FilterReason.php`
- Modify:
  - `app/Services/PageViewTracker.php`
  - `app/Services/StatsService.php` (`trackPageView`, new `trackFiltered`, `aggregateDay`)
  - `tests/Feature/StatsServiceTest.php`
  - new `tests/Feature/PageViewFilteringTest.php`

**Interfaces:**
- Consumes: `BotDetector`, `IpClassifier`, `IpClass`.
- Produces:
  - `enum FilterReason: string { Reload = 'reload'; Bot = 'bot'; Datacenter = 'datacenter'; Foreign = 'foreign'; }`
  - `StatsService::trackPageView(string $page, bool $fromApp = false): void`: HINCRBY `{page}`; if `$fromApp`, also HINCRBY `app`. Same day hash, never throws.
  - `StatsService::trackFiltered(FilterReason $reason): void`: HINCRBY `filtered:{value}`, never throws.
  - Aggregate JSON gains `'app' => int` and `'filtered' => array{reload:int,bot:int,datacenter:int,foreign:int}`, read from the day hash.

- [ ] **Step 1: Failing tests** in `PageViewFilteringTest`. Use `get('/')` with headers, and the fixture IP data from Task 2 (extract the compile-and-point helper into `tests/Pest.php`).

  | Test | Request | Expect |
  |---|---|---|
  | reload | cookie `autoreload=1` (unencrypted) | `filtered:reload`=1, no `home` |
  | bot | UA `Googlebot` | `filtered:bot`=1 |
  | datacenter | via proxy, `REMOTE_ADDR=172.18.0.1`, XFF = Hetzner fixture IP | `filtered:datacenter`=1 |
  | foreign | XFF = Comcast fixture IP | `filtered:foreign`=1 |
  | human web | SI IP, Safari UA | `home`=1, no `app` |
  | human app | SI IP, WebView UA with `TrolaAndroidApp` | `home`=1, `app`=1 |
  | order | Googlebot UA from a Hetzner IP | only `filtered:bot` |
  | no IP data | no data file, SI-looking IP | counts |

  In `StatsServiceTest`:
  - `aggregateDay` includes `app` and `filtered` with zeros when absent.
  - `filtered:*` and `app` fields never appear in station totals or popular pages.
- [ ] **Step 2: Run** `--filter='PageViewFiltering|StatsService'` → FAIL.
- [ ] **Step 3: Implement.**
  - `PageViewTracker::track(string $page): void` checks reload, then `BotDetector::isBot($request->userAgent())`, then `IpClassifier::classify((string) $request->ip())`, and calls `trackFiltered` or `trackPageView($page, BotDetector::isApp(...))`.
  - Keep the docblock privacy note: the IP is only classified in memory.
- [ ] **Step 4: Run** → PASS, then the full suite.
- [ ] **Step 5: Commit.** `feat: count only human views and record what the filters drop`

### Task 4: StatsInsights service (all page maths)

**Files:**
- Create: `app/Services/Stats/StatsInsights.php`, `tests/Feature/StatsInsightsTest.php`

**Interfaces:**
- Consumes:
  - `stats_agg_day_*` via `StatsService::getAggregatedDay(string $Ymd): ?array`
  - day hashes `stats_day_{Y_m_d}`
  - `Station` model (`code`, `name`, `is_direction_to_center`)
- Produces (all dates are `Carbon` "today" via `now()`; windows end **yesterday**, because today is partial):
  - `overview(): array{views: array{avg: ?float, vs_prev: ?float, vs_year: ?float, straddles: bool}, geosearch: array{same shape}, app_share: ?float, entry: array{home: float, search: float, geosearch: float, stations: float}, filtered: array{reload:int,bot:int,datacenter:int,foreign:int}}`
    - `avg` = mean of daily human views over the last 28 days present.
    - `vs_prev` / `vs_year` = % change of that mean versus the 28 days before, and versus the same 28 days shifted 364 days back.
    - Any of these is null when fewer than 14 days of either window exist.
    - `straddles` = the comparison window includes a day before `filters_live_since`.
    - `app_share` and `filtered` use only days on or after go-live; `app_share` is null if there are none.
    - `entry` = shares (0–1) of each page group in the 28 days.
  - `trend(int $months): array{days: list<array{date: string, views: ?int, avg28: ?float, last_year_avg28: ?float}>, live_since: string}`
    - `months` ∈ {3, 6, 12}; days run from today minus `months` months to yesterday.
    - `avg28` is the rolling mean over the 28 days ending that day, ignoring missing days; null if fewer than 14 are present.
    - `last_year_avg28` is the same for the date minus 364 days.
  - `stations(string $list): array{rows: list<array{name: string, codes: list<string>, per_day: float, change: ?float, weekly: list<int>}>, unused: int}`
    - `$list` ∈ {`top`, `rising`, `falling`, `least`}. Each returns 25 rows.
    - `per_day` = 28-day views / 28.
    - `change` = (share now / share prev − 1) × 100, where share = group views / all station views in that window. Null if the group had 0 views in the previous window, or the two windows' combined views are below 56.
    - `rising`/`falling` include only non-null `change`, sorted desc/asc.
    - `least` sorts ascending by `per_day`, then name; zero-view groups first.
    - `weekly` = 12 sums of 7 days, oldest first.
    - `unused` = the number of groups with 0 views in 28 days.
  - `lookup(string $term): ?array{name: string, codes: list<array{code: string, to_center: bool, share: ?float}>, per_day: float, change: ?float, rank: int, of: int, days: list<array{date: string, views: int, avg28: ?float}>}`
    - The term matches by exact code, else by normalised name: exact match first, then the first prefix match alphabetically. Null when nothing matches.
    - `days` covers the last 365 days. `rank` is by 28-day views among all groups (1 = most).
  - Grouping key: `Str::lower(Str::ascii(trim($name)))`, the same as `SyncStations::nameKey`. Extract it to a public static `App\Support\StationName::key(string $name): string` and use it in both places.
  - Human views per day from an aggregate = `home + search + geosearch + stations` (missing aggregate → null).
  - Station reads use `Redis::pipeline()` with `hgetall` / `hmget` on `stats_day_{Y_m_d}` keys. **No SCAN.** Fields per code: `station_{code}`, `station_{code}_all`.

- [ ] **Step 1: Failing tests.** Seed with helpers: `seedAgg(string $Ymd, int $home, int $stations, array $extra = [])` writes `stats_agg_day_*` JSON; `seedStationViews(string $Ymd, array $fieldCounts)` HSETs into `stats_day_*`. Freeze time with `$this->travelTo('2026-12-31 12:00')` and set `stats.filters_live_since` to `2026-11-07`.
  - `overview`:
    - 56 days of 100 then 28 days of 110 human views gives `avg` 110.0 and `vs_prev` 10.0.
    - Year-ago days missing gives `vs_year` null.
    - A window containing 2026-11-06 gives `straddles` true.
    - With `app` 30 of 120 on post-go-live days, `app_share` is 0.25.
    - An aggregate without `app`/`filtered` keys doesn't throw and is ignored for `app_share`.
  - `trend(3)`:
    - The length equals the day count from 2026-09-30 to 2026-12-30 inclusive.
    - A missing day gives `views` null and does not drag `avg28` to a lower value.
    - `live_since` is `'2026-11-07'`.
  - `stations`:
    - Two codes with the same name (`'Konzorcij'`, `600011`/`600012`) form one group summing `station_600011` + `station_600012_all`.
    - A station with no views is counted in `unused` and appears first in `least`.
    - Doubling one group's share gives `change` 100.0 and puts it first in `rising`.
    - A group with combined views below 56 has `change` null and is absent from `rising`.
  - `lookup`:
    - `'600011'` and `'konzorcij'` both return the group; `'nonexistent'` returns null.
    - `rank` is correct among 3 groups.
    - The direction share split per code sums to 1.
- [ ] **Step 2: Run** `tests/Feature/StatsInsightsTest.php` → FAIL.
- [ ] **Step 3: Implement** to the interface above.
- [ ] **Step 4: Run** → PASS, then the full suite + stan.
- [ ] **Step 5: Commit.** `feat: compute stats insights from aggregates and day hashes`

### Task 5: `/stats` page, privacy note, docs

**Files:**
- Create:
  - `app/Http/Requests/StatsRequest.php`
  - `resources/views/components/stats-line-chart.blade.php`
  - `resources/views/components/stats-sparkline.blade.php`
  - `resources/views/components/stats-change-badge.blade.php`
- Modify:
  - `app/Http/Controllers/StatsController.php`
  - `resources/views/stats.blade.php` (rewrite)
  - `tests/Feature/StatsPageTest.php`
  - `privacy_policy.md`
  - `CLAUDE.md` (stats section)
  - `README.md` deploy note
- Delete once unused (check with grep): `resources/views/components/daily-stat-card.blade.php`, `metric-card.blade.php`, `popular-page-card.blade.php`; and `StatsService::getPopularPages` plus its private helpers, together with their tests. Removing tests of deleted features is allowed: the owner asked for the page to be replaced.

**Interfaces:**
- Consumes: `StatsInsights::overview()`, `trend()`, `stations()`, `lookup()`.
- Produces:
  - `StatsRequest` methods:
    - `months(): int` (3|6|12, default 3)
    - `list(): string` (top|rising|falling|least, default top)
    - `stationTerm(): ?string` (trimmed, max 100 characters, null if empty)

    Its `rules()` must never fail validation in a way that redirects. Use `prepareForValidation` to coerce bad and array inputs to defaults, or do no validation and only sanitise in the accessors. `/stats` must always return 200.
  - Controller caching:
    - `Cache::remember('stats_page_v2', 600, fn)` for `overview` + `trend(12)` (slice to 3/6 in PHP)
    - `Cache::remember("stats_list_{$list}", 600, …)`
    - `Cache::remember('stats_lookup_'.md5(StationName::key($term)), 600, …)`
    - Old `stats_page` key: leave it to expire.

- [ ] **Step 1: Failing tests** in `StatsPageTest`. Seed with Task 4's helpers.
  - `get('/stats')` is 200 and shows `Ali se uporablja?`, `Trend`, `Postaje`, `Poišči postajo`, plus the formatted average and a `+10 %` badge for the overview seed.
  - `?seznam=rising` lists the rising group first.
  - `?obdobje=12` renders. `?obdobje=abc` and `?seznam[]=x` are 200 with defaults.
  - `?postaja=konzorcij` shows `Konzorcij` and its rank. `?postaja=zzz` shows `Postaja ni najdena`.
  - A request to `/stats` does not increment any `stats_day_*` field.
  - With Redis down (`Redis::shouldReceive(...)->andThrow`), `/stats` still returns 200. Keep and adapt the existing test.
- [ ] **Step 2: Run** `tests/Feature/StatsPageTest.php` → FAIL.
- [ ] **Step 3: Implement the view** following the mockup's order and structure, in Slovenian, with the existing layout (`@extends` the same layout as the current `stats.blade.php`):
  - **Section 1 "Ali se uporablja? (zadnjih 28 dni)":**
    - Cards for "Ogledi na dan" (avg + badges "glede na prejšnjih 28 dni" / "glede na lani"), "Iskanja po bližini na dan" (same) and "Od kod" (Splet / Android aplikacija %).
    - Entry shares as a short line.
    - A small grey line "Izločeno: N samodejnih osvežitev, N botov, N podatkovnih centrov, N izven Evrope".
    - When `straddles`, a grey note "vključuje dneve pred filtri (od {date})".
  - **Section 2 "Trend":**
    - The `stats-line-chart` component: viewBox `0 0 480 140`, `preserveAspectRatio="none"`, width 100%. Daily views as a faint grey polyline, avg28 as a thick emerald-700 line, last year dashed grey.
    - Null values break the polyline into segments.
    - A dotted vertical marker at `live_since` with a tiny label "filtri".
    - Range links `3 m / 6 m / 12 m` with the active one as a filled pill.
    - A legend line under the chart.
  - **Section 3 "Postaje (zadnjih 28 dni glede na prejšnjih 28)":**
    - Pill links "Najbolj uporabljene / V porastu / V upadu / Najmanj uporabljene".
    - A table: name (link to `?postaja=` + current params), na dan, sprememba (badge), 12 tednov (sparkline 70×16).
    - On `least`, a line "N postaj brez ogledov".
  - **Section 4 "Poišči postajo":**
    - A GET form with `name="postaja"` (keep the `obdobje`/`seznam` hidden inputs).
    - The result card shows name, codes with direction labels "proti centru" / "iz centra" and their share, a line chart of `days`, `X na dan`, the change badge, and "mesto #R od N".
  - `stats-change-badge`: `+12 %` on `bg-green-100 text-green-800`, negative on `bg-red-100 text-red-800`, null → `—` in grey.
  - Numbers use Slovenian formatting: `number_format($n, 0, ',', '.')`.
  - **No left-border accents. No JS.**
- [ ] **Step 4: Privacy and docs.**
  - Add one sentence to `privacy_policy.md` in its language: bot, datacenter and non-European traffic is excluded from the counts by checking the IP address in memory only; IP addresses are never stored.
  - Update the CLAUDE.md stats bullets with the new fields and services.
  - Add a README deploy note: run `php artisan stats:update-ip-data` once before go-live, then `php artisan stats:aggregate --days=800 --force`.
- [ ] **Step 5: Run** the full suite, stan and pint. Clear stale caches (`php artisan route:clear && php artisan view:clear`). Take a phone-width screenshot of `/stats` with seeded data: from `public/`, run `php -S 127.0.0.1:8899 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`, then use Chrome headless `--window-size=390,2400 --screenshot`. Look at it and fix layout problems.
- [ ] **Step 6: Commit.** `feat: rebuild /stats around human views, trends and stations`
