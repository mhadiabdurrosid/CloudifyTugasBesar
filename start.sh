#!/bin/bash
set -e

echo "🚀 Starting Cloudify application..."

echo "🔍 Checking database environment..."

DB_HOST=${MYSQLHOST:-""}
DB_PORT=${MYSQLPORT:-3306}

if [ -z "$DB_HOST" ]; then
    echo "⚠️  No database environment (MYSQLHOST missing)"
else
    echo "📡 Database: $DB_HOST:$DB_PORT"
fi

echo "⏳ Waiting for database..."
for i in {1..10}; do
    php -r "
        \$host = getenv('MYSQLHOST');
        \$port = getenv('MYSQLPORT') ?: 3306;
        if (!\$host) exit(1);
        \$c = @fsockopen(\$host, \$port, \$e, \$s, 2);
        if (\$c) { fclose(\$c); exit(0); }
        exit(1);
    " \
    && { echo "✅ DB ready"; break; }

    sleep 2
done

echo "📊 Skipping schema initialization (existing Cloudify DB)"
echo "👥 Skipping user auto-setup"

echo "🌐 Starting FrankenPHP..."
exec frankenphp run --config /app/Caddyfile
