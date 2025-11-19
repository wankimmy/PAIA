# Personal AI Assistant (PAIA)

A self-hostable personal AI assistant built with Laravel 11, Vue 3, and Docker.

## Features

- **Secure Authentication**: Email + OTP login (no passwords)
- **AI Chat**: Text-based chat with local LLM via Ollama
- **Voice Commands**: Record voice, transcribe, and execute actions
- **Task Management**: Create, edit, and manage tasks with due dates and tags
- **Secure Notes**: Encrypted notes storage
- **Password Vault**: Secure password storage with encryption
- **Web Push Notifications**: Get reminders via browser notifications
- **PWA Support**: Install as a Progressive Web App
- **Data Export**: Download all your data as a text file

## Tech Stack

- **Backend**: Laravel 11, SQLite, Sanctum
- **Frontend**: Vue 3, Vite, Pinia, Vue Router
- **AI**: Ollama (local LLM)
- **STT**: Whisper (external service)
- **Containerization**: Docker, Docker Compose, Nginx

## Prerequisites

- Docker and Docker Compose
- Ollama running locally (default: `http://localhost:11434`)
- Whisper STT service (optional, can be mocked)

## Setup

1. **Clone the repository**
   ```bash
   git clone <your-repo-url>
   cd PAIA
   ```

2. **Configure environment**
   ```bash
   cd backend
   cp env.template .env
   # Edit .env with your settings
   # Generate APP_KEY: php artisan key:generate
   ```
   
   For frontend:
   ```bash
   cd frontend
   cp env.template .env.local
   # Edit .env.local with your settings
   ```

3. **Start services**
   ```bash
   docker-compose up -d
   ```

4. **Run migrations**
   ```bash
   docker-compose exec backend php artisan migrate
   ```

5. **Install frontend dependencies** (if not using Docker)
   ```bash
   cd frontend
   npm install
   npm run dev
   ```

6. **Access the application**
   - Frontend: http://localhost:3000
   - Backend API: http://localhost:8000

## Configuration

### Backend (.env)

```env
APP_NAME="Personal AI Assistant"
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite

OLLAMA_BASE_URL=http://host.docker.internal:11434
OLLAMA_MODEL=llama3.2

WHISPER_STT_URL=http://host.docker.internal:9000/transcribe

VAPID_PUBLIC_KEY=your_vapid_public_key
VAPID_PRIVATE_KEY=your_vapid_private_key
VAPID_SUBJECT=mailto:admin@example.com
```

### Frontend (.env)

```env
VITE_API_URL=http://localhost:8000
VITE_VAPID_PUBLIC_KEY=your_vapid_public_key
```

## Usage

1. **Login**: Enter your email, receive OTP, verify and login
2. **Tasks**: Create and manage tasks with due dates
3. **Notes**: Store encrypted notes
4. **Passwords**: Securely store passwords
5. **AI Chat**: Chat with your AI assistant
6. **Voice Commands**: Record voice to create tasks, notes, etc.
7. **Export**: Download all your data

## Development

### Backend
```bash
docker-compose exec backend php artisan migrate
docker-compose exec backend php artisan tinker
```

### Frontend
```bash
cd frontend
npm run dev
```

## Production

1. Build frontend:
   ```bash
   cd frontend
   npm run build
   ```

2. Update docker-compose.yml for production settings

3. Set proper environment variables

## Important Notes

- **PWA Icons**: Replace `frontend/public/pwa-192x192.png` and `frontend/public/pwa-512x512.png` with actual icon images for PWA functionality
- **VAPID Keys**: Generate VAPID keys for Web Push notifications (see SETUP.md)
- **Ollama**: Ensure Ollama is running and the model (`llama3.2`) is pulled before using AI features
- **STT Service**: The Whisper STT service is optional. You can mock it or set up your own service

## Project Structure

```
PAIA/
├── backend/          # Laravel API
├── frontend/         # Vue 3 SPA/PWA
├── nginx/            # Nginx configuration
├── docker-compose.yml
└── README.md
```

## License

MIT

