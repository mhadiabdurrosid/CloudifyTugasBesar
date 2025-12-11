#!/bin/bash
set -e

echo "🚀 Starting Cloudify application..."

DB_HOST=${MYSQLHOST:-""}
DB_PORT=${MYSQLPORT:-3306}

echo "📡 DB: $DB_HOST:$DB_PORT"

echo "⏳ Waiting for DB..."
for i in {1..10}; do
    if php -r "
        \$h=getenv('MYSQLHOST');
        \$p=getenv('MYSQLPORT');
        if (\$h && @fsockopen(\$h,\$p,\$errno,\$errstr,3)) exit(0);
        exit(1);
    "; then
        echo "✅ DB ready"
        break
    fi
    sleep 2
done

echo "📊 Skipping schema initialization"
echo "👥 Skipping user auto-setup"

echo "🌐 Starting FrankenPHP..."
exec frankenphp run --config /Caddyfile
