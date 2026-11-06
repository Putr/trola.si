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
**This overrides the Boost rule below about following existing conventions:** follow the
*cleaned-up* code (thin controllers, `app/Services`, `app/Actions`), not the legacy leftovers.

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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
