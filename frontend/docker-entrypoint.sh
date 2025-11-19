#!/bin/sh
set -e

echo "=========================================="
echo "Starting PAIA Frontend Setup..."
echo "=========================================="

# Install dependencies if node_modules doesn't exist
if [ ! -d "node_modules" ] || [ ! -f "node_modules/.package-lock.json" ]; then
    echo "Installing npm dependencies..."
    npm install
fi

echo "=========================================="
echo "Frontend setup complete!"
echo "=========================================="

# Execute the main command
exec "$@"
