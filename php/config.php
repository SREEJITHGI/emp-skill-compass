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

// Establish database connection
try {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("ERROR: Could not connect to database. " . $e->getMessage());
}
