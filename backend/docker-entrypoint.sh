#!/bin/bash
set -e

echo "=========================================="
echo "Starting PAIA Backend Setup..."
echo "=========================================="

# Ensure database directory exists
echo "Setting up database directory..."
mkdir -p /var/www/html/database
touch /var/www/html/database/database.sqlite

# Set proper permissions
echo "Setting file permissions..."
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html/storage
chmod -R 755 /var/www/html/bootstrap/cache
chmod -R 755 /var/www/html/database
chmod 664 /var/www/html/database/database.sqlite

# Install/update dependencies if needed
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader
fi

# Setup .env file if it doesn't exist
if [ ! -f ".env" ]; then
    if [ -f "env.template" ]; then
        echo "Creating .env file from env.template..."
        cp env.template .env
    else
        echo "Warning: .env file not found and env.template doesn't exist."
        echo "Please create a .env file manually."
    fi
fi

# Generate app key if .env exists but key is missing
if [ -f ".env" ]; then
    if ! grep -q "APP_KEY=base64" .env || grep -q "APP_KEY=$" .env; then
        echo "Generating application key..."
        php artisan key:generate --force || true
    fi
fi

# Ensure Ollama model is installed (runs in background, non-blocking)
if [ ! -z "${OLLAMA_BASE_URL}" ] && [ ! -z "${OLLAMA_MODEL}" ]; then
    (
        echo "Checking Ollama model ${OLLAMA_MODEL} installation..."
        MAX_RETRIES=30
        RETRY_COUNT=0
        until curl -f "${OLLAMA_BASE_URL}/api/tags" > /dev/null 2>&1; do
            RETRY_COUNT=$((RETRY_COUNT + 1))
            if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
                echo "WARNING: Ollama did not become ready. Model installation skipped."
                exit 0
            fi
            sleep 3
        done
        
        MODEL_LIST=$(curl -s "${OLLAMA_BASE_URL}/api/tags" || echo "")
        if echo "$MODEL_LIST" | grep -q "${OLLAMA_MODEL}"; then
            echo "Model ${OLLAMA_MODEL} is already installed."
        else
            echo "Model ${OLLAMA_MODEL} not found. Pulling model (this may take a while)..."
            curl -X POST "${OLLAMA_BASE_URL}/api/pull" -d "{\"name\":\"${OLLAMA_MODEL}\"}" --no-buffer -s > /dev/null 2>&1 || true
            echo "Model ${OLLAMA_MODEL} installation initiated (running in background)."
        fi
    ) &
fi

# Run migrations
echo "Running database migrations..."
php artisan migrate --force || echo "Migration failed or already up to date"

# Clear and optimize
echo "Optimizing application..."
php artisan config:clear || true
php artisan route:clear || true
php artisan cache:clear || true

# Cache for production-like performance (optional, can be disabled in dev)
if [ "${APP_ENV}" != "local" ]; then
    php artisan config:cache || true
    php artisan route:cache || true
fi

echo "=========================================="
echo "Backend setup complete!"
echo "=========================================="

# Execute the main command
exec "$@"

