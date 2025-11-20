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
        
        # Health check with timeout and logging
        until HTTP_CODE=$(curl -f -s -o /dev/null -w "%{http_code}" \
            --connect-timeout 5 \
            --max-time 10 \
            "${OLLAMA_BASE_URL}/api/tags" 2>&1) && [ "$HTTP_CODE" = "200" ]; do
            RETRY_COUNT=$((RETRY_COUNT + 1))
            if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
                echo "ERROR: Ollama health check failed after ${MAX_RETRIES} retries (last HTTP status: ${HTTP_CODE:-unknown}). Model installation skipped."
                exit 0
            fi
            sleep 3
        done
        echo "SUCCESS: Ollama health check passed (HTTP ${HTTP_CODE})"
        
        # Check if model is already installed
        MODEL_LIST=$(curl -s \
            --connect-timeout 5 \
            --max-time 10 \
            "${OLLAMA_BASE_URL}/api/tags" 2>&1)
        CURL_EXIT_CODE=$?
        
        if [ $CURL_EXIT_CODE -ne 0 ]; then
            echo "ERROR: Failed to fetch model list (curl exit code: ${CURL_EXIT_CODE}). Model installation skipped."
            exit 0
        fi
        
        if echo "$MODEL_LIST" | grep -q "${OLLAMA_MODEL}"; then
            echo "SUCCESS: Model ${OLLAMA_MODEL} is already installed."
        else
            echo "Model ${OLLAMA_MODEL} not found. Pulling model (this may take a while)..."
            
            # Determine if we should show progress (development) or hide it (production)
            if [ "${APP_ENV}" = "local" ] || [ "${APP_ENV}" = "development" ]; then
                # Development: show progress
                PULL_RESPONSE=$(curl -X POST "${OLLAMA_BASE_URL}/api/pull" \
                    -d "{\"name\":\"${OLLAMA_MODEL}\"}" \
                    --no-buffer \
                    --connect-timeout 10 \
                    --max-time 3600 \
                    -w "\nHTTP_CODE:%{http_code}" 2>&1)
                PULL_EXIT_CODE=$?
                PULL_HTTP_CODE=$(echo "$PULL_RESPONSE" | grep -o "HTTP_CODE:[0-9]*" | cut -d: -f2 || echo "unknown")
                
                if [ $PULL_EXIT_CODE -eq 0 ] && [ "$PULL_HTTP_CODE" = "200" ]; then
                    echo "SUCCESS: Model ${OLLAMA_MODEL} pull started successfully (HTTP ${PULL_HTTP_CODE})"
                    echo "$PULL_RESPONSE" | grep -v "HTTP_CODE:"
                else
                    echo "ERROR: Model ${OLLAMA_MODEL} pull failed (curl exit code: ${PULL_EXIT_CODE}, HTTP status: ${PULL_HTTP_CODE})"
                    echo "$PULL_RESPONSE" | grep -v "HTTP_CODE:"
                fi
            else
                # Production: silent background pull
                PULL_RESPONSE=$(curl -X POST "${OLLAMA_BASE_URL}/api/pull" \
                    -d "{\"name\":\"${OLLAMA_MODEL}\"}" \
                    --no-buffer \
                    --connect-timeout 10 \
                    --max-time 3600 \
                    -s \
                    -w "\nHTTP_CODE:%{http_code}" 2>&1)
                PULL_EXIT_CODE=$?
                PULL_HTTP_CODE=$(echo "$PULL_RESPONSE" | grep -o "HTTP_CODE:[0-9]*" | cut -d: -f2 || echo "unknown")
                
                if [ $PULL_EXIT_CODE -eq 0 ] && [ "$PULL_HTTP_CODE" = "200" ]; then
                    echo "SUCCESS: Model ${OLLAMA_MODEL} pull initiated successfully (HTTP ${PULL_HTTP_CODE}, running in background)"
                else
                    echo "ERROR: Model ${OLLAMA_MODEL} pull failed to start (curl exit code: ${PULL_EXIT_CODE}, HTTP status: ${PULL_HTTP_CODE})"
                fi
            fi
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

