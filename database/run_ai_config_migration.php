<?php

/**
 * Safe, additive runner for the AI Configuration tables and chat log columns.
 *
 * Usage (from the project root):
 *   php database/run_ai_config_migration.php
 *
 * This intentionally does NOT run database/Migrator.php, because the existing
 * 001_create_initial_schema.sql drops all tables before recreating them.
 * Statements here are idempotent: running it twice is harmless.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This migration runner is CLI-only.\n");
}

require_once __DIR__ . '/../app/Config/Config.php';

$files = [
    __DIR__ . '/migrations/010_create_ai_configuration_tables.sql',
    __DIR__ . '/migrations/011_extend_ai_chat_logs.sql',
    __DIR__ . '/migrations/012_add_ai_api_key_column.sql',
];

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

foreach ($files as $file) {
    $sql = file_get_contents($file);

    if ($sql === false) {
        fwrite(STDERR, "Could not read migration file: {$file}\n");
        exit(1);
    }

    echo "--- Running " . basename($file) . " ---\n";

    $statements = array_filter(
        array_map('trim', explode(';', (string) preg_replace('/^\s*--.*$/m', '', $sql)))
    );

    try {
        foreach ($statements as $statement) {
            if ($statement === '') {
                continue;
            }
            // query() + closeCursor() supaya statement no-op "SELECT '... exists'"
            // tidak meninggalkan result set aktif pada koneksi berikutnya.
            $stmt = $pdo->query($statement);
            if ($stmt instanceof PDOStatement) {
                $stmt->fetchAll();
                $stmt->closeCursor();
            }
            echo "  OK: " . strtok($statement, "\n") . "\n";
        }
    } catch (PDOException $e) {
        fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
        fwrite(STDERR, "Statement: {$statement}\n");
        exit(1);
    }
}

echo "AI configuration migration finished successfully.\n";
