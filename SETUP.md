# Setup Guide

## Quick Start

### 1. Prerequisites

- Docker and Docker Compose installed
- Ollama running locally (or accessible at `http://host.docker.internal:11434`)
- Node.js 20+ (for local frontend development, optional)

### 2. Backend Setup

```bash
cd backend

# Copy environment file
cp .env.example .env

# Edit .env with your settings:
# - Set APP_KEY (run: php artisan key:generate)
# - Configure OLLAMA_BASE_URL
# - Configure WHISPER_STT_URL (optional)
# - Set VAPID keys for push notifications (optional)

# If running locally (not Docker):
composer install
php artisan key:generate
php artisan migrate
```

### 3. Frontend Setup

```bash
cd frontend

# Install dependencies
npm install

# Create .env file
echo "VITE_API_URL=http://localhost:8000" > .env.local
echo "VITE_VAPID_PUBLIC_KEY=your_vapid_public_key" >> .env.local

# Run development server (if not using Docker)
npm run dev
```

### 4. Docker Setup

```bash
# From project root
docker-compose up -d

# Run migrations
docker-compose exec backend php artisan migrate

# View logs
docker-compose logs -f
```

### 5. Generate VAPID Keys (for Web Push)

You can generate VAPID keys using online tools or:

```bash
# Install web-push globally
npm install -g web-push

# Generate keys
web-push generate-vapid-keys
```

Add the keys to:
- Backend `.env`: `VAPID_PUBLIC_KEY` and `VAPID_PRIVATE_KEY`
- Frontend `.env.local`: `VITE_VAPID_PUBLIC_KEY`

### 6. Ollama Setup

1. Install Ollama: https://ollama.ai
2. Pull a model:
   ```bash
   ollama pull llama3.2
   ```
3. Start Ollama (it runs on port 11434 by default)

### 7. Whisper STT (Optional)

You can set up a Whisper STT service or mock it. For development, you can create a simple mock endpoint that returns:

```json
{
  "text": "transcribed text here"
}
```

## First Login

1. Open http://localhost:3000
2. Enter your email
3. Check your email for the OTP code
4. Enter the code to login

## Troubleshooting

### Database Issues

```bash
# Recreate database
docker-compose exec backend rm database/database.sqlite
docker-compose exec backend php artisan migrate
```

### Permission Issues

```bash
# Fix storage permissions
docker-compose exec backend chmod -R 775 storage bootstrap/cache
docker-compose exec backend chown -R www-data:www-data storage bootstrap/cache
```

### Frontend Build Issues

```bash
cd frontend
rm -rf node_modules dist
npm install
npm run build
```

## Development

### Backend Commands

```bash
# Tinker
docker-compose exec backend php artisan tinker

# Create migration
docker-compose exec backend php artisan make:migration create_example_table

# Clear cache
docker-compose exec backend php artisan cache:clear
docker-compose exec backend php artisan config:clear
```

### Frontend Commands

```bash
cd frontend
npm run dev      # Development server
npm run build    # Production build
npm run preview  # Preview production build
```

## Production Deployment

1. Set `APP_ENV=production` and `APP_DEBUG=false` in backend `.env`
2. Build frontend: `npm run build`
3. Update nginx configuration for production
4. Set up SSL certificates
5. Configure proper domain names
6. Set up backup for SQLite database

