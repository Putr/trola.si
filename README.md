# Trola.si

## Deployment

1. Copy the configuration files and modify them for your environment:

   ```bash
   cp .env.example .env
   # Modify .env with your settings
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
   npm run build
   ```

5. Install and set up PHP dependencies:

   ```bash
   composer run dev
   ```

## Services

### PHPMyAdmin

<http://localhost:8080/>
