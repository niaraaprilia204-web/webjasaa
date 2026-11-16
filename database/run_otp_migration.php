<?php

/**
 * Safe, additive runner for the WhatsApp OTP schema change.
 *
 * Usage (from the project root):
 *   php database/run_otp_migration.php
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
    fwrite(STDERR, "Database connection failed. Check DB_* configuration.\n");
    exit(1);
}

$sqlFile = __DIR__ . '/migrations/002_create_otp_verification.sql';
$sql = file_get_contents($sqlFile);

if ($sql === false) {
    fwrite(STDERR, "Could not read migration file.\n");
    exit(1);
}

try {
    $pdo->exec($sql);

    $tableCheck = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'otp_verifications'"
    )->fetchColumn();

    $columnCheck = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'phone_verified_at'"
    )->fetchColumn();

    if ((int) $tableCheck !== 1 || (int) $columnCheck !== 1) {
        fwrite(STDERR, "Migration ran but verification failed. Check the database manually.\n");
        exit(1);
    }

    echo "OTP migration applied successfully.\n";
    echo " - table otp_verifications: OK\n";
    echo " - column users.phone_verified_at: OK\n";
} catch (PDOException $e) {
    fwrite(STDERR, "OTP migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
