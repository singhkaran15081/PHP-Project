<?php

// Simple config for DB + session

define('DB_PATH', __DIR__ . '/data/db.sqlite');

define('APP_NAME', 'Gas Inventory System');

// Ensure we use a consistent timezone
date_default_timezone_set('UTC');

// Start session for auth + flash
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// PDO connection helper
function get_db() {
    static $pdo;
    if ($pdo) {
        return $pdo;
    }

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $dsn = 'sqlite:' . DB_PATH;
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}
