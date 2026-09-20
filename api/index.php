<?php
/**
 * Vercel Serverless Gateway Router for EmpTrack
 * Routes requests to corresponding PHP scripts in the repository.
 */

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route = ltrim($requestUri, '/');

// Normalize root path to index.php
if ($route === '' || $route === 'index' || $route === 'index.html') {
    $route = 'index.php';
}

// Append .php if omitted (e.g. /dashboard -> dashboard.php)
if (!str_contains($route, '.') && file_exists(__DIR__ . '/../' . $route . '.php')) {
    $route .= '.php';
}

$baseDir = realpath(__DIR__ . '/..');
$targetPath = realpath($baseDir . '/' . $route);

// Verify file exists and is within project directory
if ($targetPath && str_starts_with($targetPath, $baseDir) && is_file($targetPath) && str_ends_with($targetPath, '.php')) {
    // Set current working directory to the target file's directory
    chdir(dirname($targetPath));
    require $targetPath;
    exit;
}

// 404 fallback or redirect to index.php
chdir($baseDir);
require $baseDir . '/index.php';
