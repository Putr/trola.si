# Stats insights: design

**Date:** 2026-11-07 · **Branch:** `feat/stats-insights` (built on the per-day Redis hash storage, commit 782ff7c)

## Why

The owner opens `/stats` to answer three questions:

1. Do people actually use trola.si?
2. Is use going up or down overall, with day-to-day noise smoothed out?
3. Which stations do people use, which don't they, and how is that changing? This includes looking up one station and seeing its trend.

Today's page can't answer these honestly. It counts every page load, including auto-reloads (fixed 2026-11-06), bots, scanners and datacenter crawlers. It shows only 14 raw days, and its station numbers were broken by the Redis prefix bug (fixed by the hash storage branch). The owner plans local promotion and needs to see whether station usage moves. The page should not know or care what the promotion is.

## Goals

- Count **human page views**, not people. Exact numbers don't matter; trends do.
- Exclude:
  - auto-reloads (already done)
  - bots that announce themselves in the User-Agent
  - requests from datacenter/hosting networks
  - requests from IPs outside Europe
- Split counted views into **web** and **Android app**. The app appends `TrolaAndroidApp` to the WebView User-Agent (`../trola.si_android/app/src/main/java/si/andree/trolasi/MainActivity.kt:87`).
- Show **geosearches** as their own trended metric. Only real phones with location on can do one, so it's the strongest human signal.
- Show how people reach a station: search, nearby (geosearch), direct link, app. This falls out of the same per-page counts.
- One public, mobile-first `/stats` page in Slovenian, with four sections (approved mockup: `docs/superpowers/specs/2026-11-07-stats-insights-mockup.html`, authoritative for structure and order, not for exact numbers or colours):
  1. **Is it used?**
     - Average human views/day over the last 28 days, versus the previous 28 days and versus the same 28 days a year ago.
     - Web/app split.
     - Geosearches/day with the same comparisons.
     - What the filters removed in the period, by reason: reloads, bots, datacenter, outside Europe.
  2. **Trend.**
     - Daily human views as a faint line, a 28-day rolling average as the main line, and the same period last year dashed.
     - Range switch: 3 / 6 / 12 months.
     - The day the filters went live is marked on the chart.
  3. **Stations.**
     - Tabs: most used, rising, falling, least used/unused.
     - Each row shows views/day over the last 28 days, the change versus the previous 28 days, and a 12-week sparkline.
     - Stations are grouped by name, so both directions count together. Clicking a row opens the lookup for that station.
  4. **Look up a station.**
     - Search by name or code.
     - Shows the smoothed trend, the change, the split between directions (toward/away from the centre) and the rank among stations.

## Anti-goals (failure even if it works)

- Storing **anything** about an individual visitor:
  - no IPs, not even hashed or truncated
  - no cookies, no fingerprints, no per-visitor IDs
  - no IPs in logs written by our code

  The IP is looked up in memory during the request and then discarded. `privacy_policy.md` must stay true.
- Any third-party service called at request time, and any external script or analytics on the page.
- Making the public pages slower. The tracking work per request must stay tiny: in-memory range lookups plus one or two Redis increments.
- A heavy `/stats` page. No chart library that adds hundreds of KB. Server-rendered inline SVG is enough, with at most a few lines of vanilla JS for tabs, range switch and lookup. It must still render in old phones and the Android WebView; see the browser floor note in CLAUDE.md.

## Decided

- **Unit:** page views. Views keep the page id scheme the hash storage already uses: `home`, `search`, `geosearch`, `not_found`, `station_{code}`, `station_{code}_all`.
- **Filter order**, each dropped view incrementing one "filtered" counter for its reason (first reason that matches wins):
  1. auto-reload (existing `autoreload` cookie)
  2. self-announced bot (User-Agent)
  3. datacenter/hosting IP
  4. IP outside Europe
- **History stays.** Existing counts were collected before the filters existed. The trend shows a step on the filter go-live date, and the page marks that date. Nothing old is deleted or rewritten.
- **Data lists** for IP → country and IP → hosting network are free public files that need no account, stored locally and refreshed monthly by the scheduler. A failed refresh keeps the previous list and reports the error. The lists are data, not a Composer dependency.
- `/stats` stays **public**: it only shows totals.
- `/stats` renders from **daily aggregates**, never from scanning raw counters. It stays cached; up to 10 minutes stale is fine.
- **Real client IP:** production is host nginx → container nginx → php-fpm. Host nginx sets `X-Forwarded-For`/`X-Real-IP` (`/var/www/trola.si/etc/nginx.conf` on the server), but Laravel doesn't trust any proxy yet. Trust exactly the local and Docker-private proxy hops, so `request()->ip()` is the visitor and spoofed headers from the internet aren't believed.

## Constraints

- Laravel 13 / PHP 8.4, Pest feature tests, Larastan level 5, Pint. TDD for the filter logic, the IP range lookup and the aggregation maths.
- No new Composer or npm dependencies without the owner's approval. If a range-lookup library seems necessary, stop and ask.
- Slovenian for all user-facing copy; code in English.
- Redis key formats in production hold history.
  - New keys are fine.
  - Existing `stats_day_{Y_m_d}`, `stats_agg_day_{Y_m_d}` and `stats_avg_dow_{n}` must keep working.
  - If the aggregate JSON gains fields, old aggregates without them must still render.
- Tests never hit the network. Data list downloads are faked; tests use tiny fixture lists.
- Production runs in Docker on the VPS (see the project memory and README). The data lists must live somewhere the container can read and the scheduler can write, e.g. `storage/app/`.

## Left to the builder

- Which public datasets to use, provided they're free, need no account, allow this use and cover IPv4 and IPv6. Candidates:
  - iptoasn.com "ip2asn-combined" (range → ASN + country) for both country and network
  - a maintained public list of hosting/datacenter ASNs or ranges
- Verify the licence and the download URL, and record the choice in CLAUDE.md.
- The exact definition of "Europe" as a country-code list. Geographic Europe, including the Balkans, UK, CH, NO, IS, TR and CY, is the intent: tourists in Ljubljana roam on European SIMs.
- The bot User-Agent pattern list. At minimum: bot/crawl/spider/slurp, link previewers, uptime monitors, HTTP libraries (curl, python-requests, Go-http-client and similar), headless browsers that say so, and an empty User-Agent.
- The data structure for fast in-request range lookups, e.g. sorted ranges + binary search, compiled once into a PHP array file that opcache can hold.
- How the web/app split and filtered counters are stored, e.g. extra fields in the day hash like `app:station_600011` and `filtered:bot`. Keep the per-day hash design.
- How the 28-day/12-week windows and "rising/falling" are computed. Rising/falling should ignore stations with tiny numbers so one extra view isn't "+100%".
- The visual design within the approved mockup's structure, following the project's existing Tailwind 3 look. No coloured left-border accent stripes; use small tinted badges for +/−.

## Calls made after the builder check (owner said "do everything autonomously")

1. **Go-live straddle.**
   - The go-live date lives in config: `stats.filters_live_since`, env `STATS_FILTERS_SINCE`, default `2026-11-07`.
   - Headline comparisons whose window includes days before it still show, with a small "vključuje dneve pred filtri" note.
   - Station change is always measured as the change in the station's **share of all station views**, not raw views. That cancels the filter step and overall traffic swings, and answers "which stations gain relative to others".
   - The web/app split and the filtered counts only exist from go-live onwards.
2. **Mockup.** Committed at `docs/superpowers/specs/2026-11-07-stats-insights-mockup.html`. It is authoritative for structure and order.
3. **No-JS page.**
   - Tabs, range switch and lookup are plain links and a GET form on `/stats`: `?seznam=top|rising|falling|least`, `?obdobje=3|6|12`, `?postaja=<name or code>`.
   - Input goes through a Form Request; bad values fall back to the defaults.
   - `/stats` is not tracked as a page view.
   - The page base is cached 10 minutes. Lookups are cached per station group.
4. **Station data source.**
   - Station tables and lookups read the per-day hashes `stats_day_{date}` directly, by key, never with SCAN, using a Redis pipeline:
     - the table: HGETALL over the last 84 days
     - the lookup: HMGET of the group's fields over 400 days
   - Totals for the trend come from `stats_agg_day_*`, which gain `app` and `filtered` fields; old aggregates without them render as "no data".
   - On deploy, aggregates are rebuilt for 800 days.
5. **Station universe.**
   - All rows of `stations`, grouped by a normalised name (lower-case, ASCII), the same normalisation the station sync uses. A group's views = sum of `station_{code}` + `station_{code}_all` over its codes.
   - "Least used" lists groups with 0 views first, then the lowest, 25 rows, plus a count of unused groups.
   - Rising/falling only rank groups with at least 56 views across both 28-day windows.
6. **App traffic gets no exemption.** Requests with `TrolaAndroidApp` go through the same filters. No bot pattern may match an ordinary Android WebView UA; a test pins this.
7. **Trusted proxies.**
   - Production: host nginx (`X-Forwarded-For` via `$proxy_add_x_forwarded_for`) → Docker bridge `172.18.0.0/16`, gateway `172.18.0.1` → container nginx → php-fpm.
   - Trust `127.0.0.1`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `::1`, `fc00::/7`, with headers `X-Forwarded-For` + `X-Forwarded-Proto` only. Symfony walks the list from the right and skips trusted hops, so the real client wins over a spoofed left-most entry.
   - The reload check runs first, so reloads cost no IP lookup.
8. **Totals and counters.**
   - "Human views" = home + search + geosearch + all station pages. `not_found` is excluded.
   - Filtered counters are one per reason per day (`filtered:reload|bot|datacenter|foreign`), not per page.
   - App views are one counter per day (`app`).
   - "How people reach a station" becomes the 28-day share of home / search / geosearch / station views. "Direct link" can't be known without a referrer, which we don't store.
9. **IP data.**
   - iptoasn.com `ip2asn-combined.tsv.gz` (PDDL v1.0, public domain, no account) is the only dataset.
   - Datacenter = AS description matching a keyword list (hosting, cloud, server, the big clouds and VPS providers) in `config/stats.php`. Not routed (ASN 0) = count.
   - **CDNs are not datacenters**: Cloudflare, Akamai and Fastly are excluded. Apple iCloud Private Relay and Cloudflare WARP exit through them, and those are real people.
   - Ranges are merged by class and compiled into packed big-endian binary strings: IPv4 ≈205k entries, IPv6 ≈89k. They go into a PHP file under `storage/app/ipdata/` that opcache holds, and lookup is a binary search.
   - A missing file means everything counts.
10. **Europe.** The country codes: AD AL AT AX BA BE BG BY CH CY CZ DE DK EE ES EU FI FO FR GB GG GI GR HR HU IE IM IS IT JE LI LT LU LV MC MD ME MK MT NL NO PL PT RO RS RU SE SI SK SM TR UA VA XK.

## Done means

- Bots, datacenter IPs, non-European IPs and reloads don't increment the counted views; each increments its filtered reason instead. Covered by feature tests with fake requests (User-Agent, `X-Forwarded-For` through trusted proxies).
- `/stats` shows the four sections with correct maths against seeded data, renders quickly from aggregates, and passes the existing pages, the smoke tests and a phone-width screenshot check.
- The privacy policy has one added sentence saying bot and foreign traffic is filtered without storing IP addresses.
- A deploy runbook note covers fetching the IP data lists before go-live.
