# Trola.si

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

## Services

### PHPMyAdmin

<http://localhost:8080/>
