#!/bin/bash
set -e

echo "🚀 Starting Cloudify application..."

echo "🔍 Detecting database configuration..."
DB_CHECK=$(php -r "
    \$host = getenv('MYSQLHOST') ?: '';
    \$port = getenv('MYSQLPORT') ?: 3306;
    echo empty(\$host) ? 'NONE' : \$host . ':' . \$port;
")

if [ "$DB_CHECK" = "NONE" ]; then
    echo "⚠️  No database configuration found!"
else
    echo "📡 Database: $DB_CHECK"
    echo "⏳ Waiting for database connection..."

    # Wait for DB
    for i in {1..15}; do
        if php -r "
            \$conn = @fsockopen(getenv('MYSQLHOST'), getenv('MYSQLPORT'), \$errno, \$errstr, 3);
            if (\$conn) { fclose(\$conn); exit(0); } else { exit(1); }
        "; then
            echo "✅ Database is ready!"
            break
        fi
        sleep 3
    done
fi

# Initialize Schema (optional)
echo "📊 Initializing database schema..."
if [ -f "/app/db/init_combined_cloudify.sql" ]; then
    php -r "
    require_once '/app/model/Koneksi.php';
    \$db = new Koneksi();
    \$conn = \$db->getConnection();
    \$sql = file_get_contents('/app/db/init_combined_cloudify.sql');
    \$conn->multi_query(\$sql);
    echo 'Schema loaded\n';
    "
else
    echo "⚠️  No schema file found, skipping..."
fi

# Optional user setup
echo "👥 Setting up users..."
if [ -f "/app/setup_users.php" ]; then
    php /app/setup_users.php
else
    echo "⚠️  setup_users.php missing, skipping..."
fi

echo "🎉 Initialization complete!"
echo "🌐 Starting FrankenPHP web server..."

exec frankenphp run --config /app/Caddyfile
