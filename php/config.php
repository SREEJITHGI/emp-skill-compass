<?php
// Database configuration with dynamic environment variable support for cloud deployment
$dbHost = getenv('DB_SERVER') ?: (getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: 'localhost'));
$dbUser = getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root'));
$dbPass = getenv('DB_PASSWORD') ?: (getenv('DB_PASS') ?: (getenv('MYSQLPASSWORD') ?: (getenv('MYSQL_ROOT_PASSWORD') ?: 'SREECARINO@2005')));
$dbName = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'skill_compass');
$dbPort = intval(getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: 3306));

// Support for single DATABASE_URL / JAWSDB_URL / CLEARDB_DATABASE_URL connection strings
$databaseUrl = getenv('DATABASE_URL') ?: (getenv('JAWSDB_URL') ?: getenv('CLEARDB_DATABASE_URL'));
if ($databaseUrl) {
    $parsed = parse_url($databaseUrl);
    if ($parsed) {
        $dbHost = $parsed['host'] ?? $dbHost;
        $dbUser = $parsed['user'] ?? $dbUser;
        $dbPass = $parsed['pass'] ?? $dbPass;
        $dbName = ltrim($parsed['path'] ?? $dbName, '/');
        $dbPort = intval($parsed['port'] ?? $dbPort);
    }
}

define('DB_SERVER', $dbHost);
define('DB_USERNAME', $dbUser);
define('DB_PASSWORD', $dbPass);
define('DB_NAME', $dbName);
define('DB_PORT', $dbPort);

// Establish database connection with automated cloud SSL support
try {
    $conn = mysqli_init();
    if (!$conn) {
        throw new Exception("mysqli_init failed");
    }

    // Enable SSL transport for remote cloud hosts (Aiven, TiDB, PlanetScale, etc.)
    $isRemote = (DB_SERVER !== 'localhost' && DB_SERVER !== '127.0.0.1' && DB_SERVER !== 'db');
    $clientFlags = 0;

    if ($isRemote) {
        if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
            $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        }
        $clientFlags = MYSQLI_CLIENT_SSL;
    }

    $connected = @$conn->real_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT, null, $clientFlags);
    
    // Check connection
    if (!$connected || $conn->connect_error) {
        throw new Exception("Connection failed: " . ($conn->connect_error ?: 'Could not connect to database host'));
    }
} catch (Exception $e) {
    die("ERROR: Could not connect to database. " . $e->getMessage());
}
