<?php

/**
 * Safe, additive runner for order payment fields.
 *
 * Usage (from the project root):
 *   php database/run_payment_migration.php
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

$columns = [
    'payment_status' => "ALTER TABLE orders ADD COLUMN payment_status ENUM('unpaid', 'pending', 'paid', 'refunded') NOT NULL DEFAULT 'unpaid' AFTER status",
    'payment_method' => "ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) NULL AFTER payment_status",
    'payment_amount' => "ALTER TABLE orders ADD COLUMN payment_amount DECIMAL(12,2) NULL AFTER payment_method",
    'paid_at'        => "ALTER TABLE orders ADD COLUMN paid_at DATETIME NULL AFTER payment_amount",
];

try {
    foreach ($columns as $column => $sql) {
        $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE " . $pdo->quote($column));
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "Column {$column} already exists.\n";
            continue;
        }
        $pdo->exec($sql);
        echo "Column {$column} added.\n";
    }
} catch (PDOException $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Payment migration completed successfully.\n";
