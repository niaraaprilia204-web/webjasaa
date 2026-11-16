<?php

/**
 * Safe, additive runner for the Admin role, chatbot history, and AI settings.
 *
 * Usage (from the project root):
 *   php database/run_admin_migration.php
 *
 * This intentionally does NOT run database/Migrator.php, because the existing
 * 001_create_initial_schema.sql drops all tables before recreating them.
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

$sqlFile = __DIR__ . '/migrations/003_add_admin_chat_settings.sql';
$sql = file_get_contents($sqlFile);

if ($sql === false) {
    fwrite(STDERR, "Could not read migration file.\n");
    exit(1);
}

// Remove line comments and split into individual statements. The migration
// deliberately contains no semicolons inside quoted values.
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$statements = array_filter(array_map('trim', explode(';', (string) $sql)));

try {
    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }
} catch (PDOException $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Admin/chat/AI settings migration completed successfully.\n";
echo "Next: create or promote an administrator with database/create_admin.php.\n";
