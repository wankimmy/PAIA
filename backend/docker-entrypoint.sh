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

