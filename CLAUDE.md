# CLAUDE.md

## What this is

**Trola.si**: real-time arrival times for Ljubljana city buses (LPP), live at https://trola.si.
Users search for a station (by name or code), find nearby stations by GPS, and see the next
arrivals, filtered by direction (to or from the city centre). A companion Android app
(`../trola.si_android`) wraps the site in a WebView. The site detects the WebView and hides the
"get the app" buttons.

### Product principles (non-negotiable)

1. **Fast and lightweight.** It has to load instantly on any phone over a bad connection. No SPA,
   no heavy JS, no new frontend frameworks.
2. **Show only what matters.** Minimal UI with the next arrivals up front.
3. **No tracking, no ads, no nonsense.** The only analytics are anonymous per-page counters
   in Redis (`StatsService`). Never add third-party analytics, cookies for tracking or ad code.
   See `privacy_policy.md`, which is a public promise.

### State of the codebase

This was vibe-coded early on and is being **cleaned up and modernised**. Treat existing code
as *legacy, not as the convention*. Don't copy its patterns (fat base `Controller`, model
accessors that call HTTP APIs, catch-all `try/catch` + log + return zero, duplicated
controller methods). When you touch an area, leave it better and covered by tests.

## Stack

- **PHP / Laravel 11** (target: Laravel 13, PHP 8.4+), Blade + Tailwind CSS 3 + vanilla JS via Vite
- **MySQL 8**: one real table, `stations` (with a FULLTEXT index on `name`)
- **Redis** (predis) for cache, sessions, queue and the page-view counters
- **Pest 3** for tests. There are currently no real tests, only the scaffold examples.
- **Laravel Boost** (MCP server, `php artisan boost:mcp`, configured in `.mcp.json`)
- Docker Compose for dev/prod (`docker-compose.yml.*-example`, `Dockerfile.example`). The real
  `docker-compose.yml` / `Dockerfile` are gitignored, per-environment copies.

## How it works

- **Routes** (`routes/web.php`): `/`, `/search`, `/geosearch`, `/navodila` (help), `/stats`, then
  the catch-all station routes `/{code}` and `/{code}/all`. The catch-alls must stay last.
- **Station data** comes from the LPP API (`app/Services/LppApiService.php`, config in
  `config/lpp.php`, `LPP_API_URL` + `LPP_API_KEY`). The key is requested from dpo@lpp.si.
- **Station refresh**: `database/seeders/LoadStations.php` (run via scheduled `db:seed` daily) pulls
  all stations, then *infers* direction and opposite-station pairs. It pairs stations by
  identical name first, then by sequential codes, and treats codes ending in `1` as
  "toward centre". This heuristic is the source of most direction bugs.
- **Arrivals** are fetched live per request (`Station::getArrivalsAttribute`) and cached for 30s
  (`app/Traits/CachesStations.php`).
- **Stats**: `StatsService::trackPageView` increments `stats:{Y_m_d}:{page}` Redis keys. The
  `stats:aggregate` command builds daily/weekly/monthly aggregates. `/stats` renders them.
- **Scheduler** (`routes/console.php`): stats aggregation (02:00 + hourly), `db:seed` (03:00),
  station cache warm (03:20), sitemap (03:30). Production needs the `schedule:run` cron.

## Commands

PHP and Composer run on the host (PHP 8.5). MySQL and Redis come from Docker Compose.

```bash
composer install && npm install
npm run dev                     # or npm run build
php artisan migrate && php artisan db:seed   # loads stations from the LPP API
php artisan app:lpp:ping        # check LPP API connectivity
php artisan stats:aggregate     # rebuild stats aggregates
php artisan test --compact [--filter=...]
vendor/bin/pint --dirty         # format changed PHP files
```

## How we work here

These are the same rules as in the other Laravel projects:

- **Every change is tested.** Write or update a Pest test and run the affected tests
  (`php artisan test --compact --filter=…`). Most tests should be feature tests. Fake the LPP API
  with `Http::fake()`, and never hit the live API from the suite. Don't delete tests without approval.
- **TDD for logic.** Use `superpowers:test-driven-development` for direction/opposite-station
  inference, arrival parsing, search ranking and stats aggregation.
- **Verify before "done".** Run the tests and exercise the change, and show the evidence. Never
  claim done on unrun code.
- **Code review after each feature.** Run `/code-review` before moving on. Don't stack work on
  unreviewed changes.
- **Don't write verification scripts or use tinker** when a test can prove it.
- **Don't change dependencies without approval.** Upgrades are planned work, not side effects.
- **Don't create documentation files unless asked.**
- **Laravel way:** `php artisan make:* --no-interaction`, named routes + `route()`, Form Requests for
  validation, constructor property promotion, explicit return types, curly braces always,
  TitleCase enum keys, PHPDoc over inline comments.
- **Run Pint** (`vendor/bin/pint --dirty`) before finishing any PHP change. Pint's Laravel preset
  is the style. Don't let an IDE reformat files (e.g. adding spaces around `.`).
- **Slovenian for all user-facing copy.** Code, comments and commits are in English.
- **Right-size subagents** with the `model-selection` skill before dispatching.

## Gotchas

- `/{code}` and `/{code}/all` match *anything*, so validate the code before doing work.
- Cache keys `station:{code}` and `station:{id}` share one namespace, and the seeder only
  invalidates by code.
- Any HTML/JS change must still work inside the Android WebView.

<!-- After the Laravel 13 upgrade, run `php artisan boost:install` (Boost v2) to append the
     generated <laravel-boost-guidelines> block and .claude/skills, as in the other projects. -->
