#!/bin/bash
set -e

echo "🚀 Starting Cloudify application..."

# Detect DB connection
echo "🔍 Checking database environment..."
DB_HOST=${MYSQLHOST:-""}
DB_PORT=${MYSQLPORT:-3306}

if [ -z "$DB_HOST" ]; then
    echo "⚠️  No database environment variables found."
else
    echo "📡 Database: $DB_HOST:$DB_PORT"
fi

# Wait for DB (optional but useful)
echo "⏳ Waiting for database..."
for i in {1..10}; do
    if php -r "
        \$host = getenv('MYSQLHOST')
        \$port = getenv('MYSQLPORT');
        if (\$host && @fsockopen(\$host, \$port, \$errno, \$errstr, 3)) exit(0);
        exit(1);
    "; then
        echo "✅ Database is reachable."
        break
    fi
    sleep 2
done

echo "📊 Skipping schema initialization (using existing Cloudify DB)"
echo "👥 Skipping user auto-setup (real users already exist)"

echo "🌐 Starting FrankenPHP server..."
exec frankenphp run --config /app/Caddyfile
