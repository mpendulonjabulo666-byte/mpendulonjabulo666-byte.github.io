<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/schema_upgrade.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // A deploy can bring code that expects newer tables/columns than the
        // database has; this brings the database up first (see
        // includes/schema_upgrade.php). Migrations get $pdo passed in, so
        // they never call back into db().
        nutritale_ensure_schema_current($pdo);
    }
    return $pdo;
}
