# Trola.si

Trola.si is a web application that provides real-time arrival information for Ljubljana city buses. It allows users to:

- Search for bus stations by name or station code
- View upcoming bus arrivals for any station
- Filter arrivals by direction (to/from city center)
- Find nearby stations using geolocation
- See estimated arrival times updated in real-time

The application uses the official LPP (Ljubljana Public Transport) API to fetch live arrival data and provides a clean, mobile-friendly interface for accessing this information.

The focus of this project was:

1. Fast and lightweight, works lightning fast on any phone
2. Optimized UI - shows only relevant information
3. No tracking, no ads, no nonsense

See it live at [trola.si](https://trola.si).

## Stack

Laravel 13 on PHP 8.4+, MySQL 8, Redis, Blade + Tailwind CSS 3 + a little vanilla JS (Vite 8).

## Prerequisites

1. An LPP API key. Email <dpo@lpp.si> to request one, then set it as `LPP_API_KEY` in `.env`.
2. PHP 8.4 or newer, Composer, Node 20+, and MySQL 8 + Redis (the Docker Compose examples provide both).

## Deployment

1. Copy the configuration files and adjust them for your environment:

   ```bash
   cp .env.example .env                                   # set LPP_API_KEY and the DB/Redis settings
   cp docker-compose.yml.prod-example docker-compose.yml  # or .dev-example
   cp Dockerfile.example Dockerfile
   docker compose up -d
   ```

2. Install dependencies and build the frontend:

   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   ```

3. Create the tables and load the stations from the LPP API:

   ```bash
   php artisan migrate --force
   php artisan stations:sync
   ```

   `stations:sync` pulls every station and infers which stations face each other and which
   one heads toward the city centre. It changes nothing if the API is down.

4. Run Laravel's scheduler every minute:

   ```bash
   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
   ```

   It refreshes the stations daily at 03:00 (`stations:sync`), aggregates the anonymous page
   counters (02:00 and hourly), warms the station cache and regenerates the sitemap.

5. After every deploy, clear the compiled caches:

   ```bash
   php artisan optimize:clear && php artisan optimize
   ```

## Development

```bash
composer install && npm install
npm run dev
composer test:up   # start throwaway MySQL + Redis for the tests (docker-compose.testing.yml)
composer test      # Pest
composer stan      # Larastan
vendor/bin/pint    # code style
```

The tests never call the real LPP API; responses are faked with `Http::fake()`.
