# Trola.si

Trola.si is a web application that provides real-time arrival information for Ljubljana city buses. It allows users to:

- Search for bus stations by name or station code
- View upcoming bus arrivals for any station
- Filter arrivals by direction (to/from city center)
- Find nearby stations using geolocation
- See estimated arrival times updated in real-time

The application uses the official LPP (Ljubljana Public Transport) API to fetch live arrival data and provides a clean, mobile-friendly interface for accessing this information.

See it live at [trola.si](https://trola.si).


## Prerequisites

Before deploying the application, you'll need:

1. LPP API Key - Send an email to <dpo@lpp.si> requesting an API key for accessing the LPP (Ljubljana Public Transport) API.
   Once received, add it to your `.env` file as `LPP_API_KEY`.

## Deployment

1. Copy the configuration files and modify them for your environment:

   ```bash
   cp .env.example .env
   # Modify .env with your settings, including your LPP_API_KEY
   ```

2. Copy, review and adjust as needed:
   - `docker-compose.yml`
   - `Dockerfile`

3. Start the Docker containers:

   ```bash
   docker-compose up -d
   ```

4. Install and build frontend dependencies:

   ```bash
   npm install
   npm run dev
   ```

5. Install and set up PHP dependencies:

   ```bash
   composer install
   ```

6. Run database migrations and seed initial data:

   ```bash
   php artisan migrate
   php artisan db:seed
   ```

   This will:
   - Create all necessary database tables
   - Load bus station data from the LPP API

7. Set up the cron job for automatic data updates:

   ```bash
   # Open crontab configuration
   crontab -e

   # Add this line to run Laravel's scheduler every minute
   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
   ```

   This will:
   - Run the database seeder daily at 4 AM to refresh station data
   - Ensure your data stays up to date with the LPP API
