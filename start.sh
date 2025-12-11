#!/bin/bash
set -e

echo "🚀 Starting Cloudify application..."

# Wait for database to be ready
echo "⏳ Waiting for database connection..."
max_attempts=30
attempt=0

while [ $attempt -lt $max_attempts ]; do
    if php -r "
        \$host = getenv('MYSQLHOST') ?: 'localhost';
        \$port = getenv('MYSQLPORT') ?: 3306;
        \$conn = @fsockopen(\$host, \$port, \$errno, \$errstr, 2);
        if (\$conn) {
            fclose(\$conn);
            exit(0);
        }
        exit(1);
    "; then
        echo "✅ Database is ready!"
        break
    fi
    attempt=$((attempt + 1))
    echo "   Attempt $attempt/$max_attempts - Database not ready yet..."
    sleep 2
done

if [ $attempt -eq $max_attempts ]; then
    echo "❌ Database connection timeout!"
    exit 1
fi

# Initialize database schema
echo "📊 Initializing database schema..."
if [ -f "/app/public/db/init_combined_cloudify.sql" ]; then
    php -r "
    try {
        require_once '/app/public/model/Koneksi.php';
        \$koneksi = new Koneksi();
        \$conn = \$koneksi->getConnection();
        
        if (\$conn) {
            \$sql = file_get_contents('/app/public/db/init_combined_cloudify.sql');
            if (\$conn->multi_query(\$sql)) {
                do {
                    if (\$result = \$conn->store_result()) {
                        \$result->free();
                    }
                } while (\$conn->next_result());
            }
            echo '✅ Database schema initialized\n';
        }
    } catch (Exception \$e) {
        echo '⚠️  Database schema setup: ' . \$e->getMessage() . '\n';
    }
    "
else
    echo "⚠️  Schema file not found, skipping..."
fi

# Run user setup
echo "👥 Setting up users..."
php /app/public/setup_users.php || echo "⚠️  User setup encountered issues, continuing..."

echo "🎉 Initialization complete!"
echo "🌐 Starting FrankenPHP web server..."

# Start FrankenPHP
exec frankenphp run --config /app/public/Caddyfile
