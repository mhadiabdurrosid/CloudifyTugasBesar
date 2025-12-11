<?php
// Environment check page for Railway deployment
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cloudify - Environment Check</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #666; margin-top: 30px; }
        .status { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; border-left: 4px solid #28a745; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
        .env-var { margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 5px; }
        .env-var strong { color: #007bff; }
        .instructions { background: #e7f3ff; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .instructions ol { margin: 10px 0; padding-left: 25px; }
        .instructions li { margin: 10px 0; }
        pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Cloudify Environment Check</h1>
        
        <?php
        // Check for database environment variables
        $databaseUrl = getenv('DATABASE_URL');
        $mysqlHost = getenv('MYSQLHOST');
        $mysqlPort = getenv('MYSQLPORT');
        $mysqlUser = getenv('MYSQLUSER');
        $mysqlPassword = getenv('MYSQLPASSWORD');
        $mysqlDatabase = getenv('MYSQLDATABASE');
        
        $hasConfig = false;
        
        if ($databaseUrl) {
            $hasConfig = true;
            echo '<div class="status success">✅ <strong>DATABASE_URL found!</strong></div>';
            echo '<div class="env-var"><strong>DATABASE_URL:</strong> ' . htmlspecialchars(preg_replace('/\/\/([^:]+):([^@]+)@/', '//***:***@', $databaseUrl)) . '</div>';
        } else {
            echo '<div class="status warning">⚠️ <strong>DATABASE_URL not found</strong></div>';
        }
        
        if ($mysqlHost || $mysqlUser || $mysqlPassword || $mysqlDatabase) {
            $hasConfig = true;
            echo '<div class="status success">✅ <strong>MySQL environment variables found!</strong></div>';
            echo '<div class="env-var"><strong>MYSQLHOST:</strong> ' . ($mysqlHost ? htmlspecialchars($mysqlHost) : '❌ Not set') . '</div>';
            echo '<div class="env-var"><strong>MYSQLPORT:</strong> ' . ($mysqlPort ?: '❌ Not set') . '</div>';
            echo '<div class="env-var"><strong>MYSQLUSER:</strong> ' . ($mysqlUser ? htmlspecialchars($mysqlUser) : '❌ Not set') . '</div>';
            echo '<div class="env-var"><strong>MYSQLPASSWORD:</strong> ' . ($mysqlPassword ? '****** (hidden)' : '❌ Not set') . '</div>';
            echo '<div class="env-var"><strong>MYSQLDATABASE:</strong> ' . ($mysqlDatabase ? htmlspecialchars($mysqlDatabase) : '❌ Not set') . '</div>';
        }
        
        if (!$hasConfig) {
            echo '<div class="status error">❌ <strong>No database configuration found!</strong></div>';
            echo '<p style="color: #721c24;">Your application cannot connect to the database because environment variables are not set.</p>';
        }
        
        // Test database connection
        if ($hasConfig) {
            echo '<h2>🔌 Database Connection Test</h2>';
            try {
                require_once __DIR__ . '/model/Koneksi.php';
                $koneksi = new Koneksi();
                $conn = $koneksi->getConnection();
                
                if ($conn && !$conn->connect_error) {
                    echo '<div class="status success">✅ <strong>Database connection successful!</strong></div>';
                    echo '<div class="env-var"><strong>Connected to:</strong> ' . htmlspecialchars($conn->host_info) . '</div>';
                    echo '<div class="env-var"><strong>Server version:</strong> ' . htmlspecialchars($conn->server_info) . '</div>';
                    
                    // Check if tables exist
                    $result = $conn->query("SHOW TABLES");
                    if ($result && $result->num_rows > 0) {
                        echo '<div class="status success">✅ <strong>Database tables found (' . $result->num_rows . ' tables)</strong></div>';
                    } else {
                        echo '<div class="status warning">⚠️ <strong>Database is empty - tables need to be created</strong></div>';
                    }
                } else {
                    echo '<div class="status error">❌ <strong>Database connection failed:</strong> ' . htmlspecialchars($conn->connect_error ? $conn->connect_error : 'Unknown error') . '</div>';
                }
            } catch (Exception $e) {
                echo '<div class="status error">❌ <strong>Connection error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
        ?>
        
        <h2>📝 How to Fix This</h2>
        <div class="instructions">
            <p><strong>You need to add environment variables to your Railway web service:</strong></p>
            
            <ol>
                <li>Go to your <strong>Railway Dashboard</strong></li>
                <li>Click on your <strong>web service</strong> (the one running this PHP app)</li>
                <li>Click on the <strong>"Variables"</strong> tab</li>
                <li>Add these environment variables:</li>
            </ol>
            
            <pre>MYSQLHOST=crossover.proxy.rlwy.net
MYSQLPORT=16870
MYSQLUSER=root
MYSQLPASSWORD=ifdumpRRCCYwVRygDxOWvSOZKsAYNfry
MYSQLDATABASE=railway</pre>
            
            <p><strong>OR</strong> use the single DATABASE_URL format:</p>
            
            <pre>DATABASE_URL=mysql://root:ifdumpRRCCYwVRygDxOWvSOZKsAYNfry@crossover.proxy.rlwy.net:16870/railway</pre>
            
            <p>⚠️ <strong>Important:</strong> Make sure you're adding these variables to your <strong>web service</strong>, not the MySQL service!</p>
            
            <p>After adding the variables, Railway will automatically restart your application.</p>
        </div>
        
        <h2>🔄 Next Steps</h2>
        <div class="info status">
            <ol>
                <li>Add the environment variables as shown above</li>
                <li>Wait for Railway to restart your service</li>
                <li>Refresh this page to verify the connection</li>
                <li>Once connected, visit your main application at <code>/index.php</code></li>
            </ol>
        </div>
        
        <p style="text-align: center; margin-top: 30px; color: #666;">
            <small>Cloudify Environment Checker | <a href="/index.php">Go to Main App</a></small>
        </p>
    </div>
</body>
</html>
