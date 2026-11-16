<?php

/**
 * Safe, additive runner for the username column.
 *
 * Usage (from the project root):
 *   php database/run_username_migration.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This migration runner is CLI-only.\n");
}

require_once __DIR__ . '/../app/Config/Config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Database connection failed. Check DB_* configuration and make sure MySQL is running.\n");
    exit(1);
}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'username'");
    $exists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exists) {
        $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(50) NULL UNIQUE AFTER email");
        echo "Username column added.\n";
    } else {
        echo "Username column already exists.\n";
    }

    $pdo->exec("UPDATE users SET username = CONCAT('user', id) WHERE username IS NULL OR username = ''");
    echo "Existing users without a username were populated.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Username migration completed successfully.\n";
