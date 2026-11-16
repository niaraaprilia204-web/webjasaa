<?php

/**
 * Create or promote an administrator account.
 *
 * Usage (from the project root):
 *   php database/create_admin.php "Nama Admin" admin@example.com "PasswordKuat123" [username]
 *
 * There is deliberately no default password and no seeded admin account.
 * Run database/run_admin_migration.php and database/run_username_migration.php
 * first so the users.role ENUM and users.username column exist.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

require_once __DIR__ . '/../app/Config/Config.php';

if ($argc < 4 || $argc > 5) {
    fwrite(STDERR, "Usage: php database/create_admin.php \"Nama Admin\" admin@example.com \"PasswordKuat123\" [username]\n");
    exit(1);
}

$name = trim((string) $argv[1]);
$email = filter_var(trim((string) $argv[2]), FILTER_SANITIZE_EMAIL);
$password = (string) $argv[3];
$username = strtolower(trim((string) ($argv[4] ?? '')));

if ($username === '') {
    $username = preg_replace('/[^a-z0-9._-]/', '', strtolower(strstr($email, '@', true) ?: 'admin'));
    $username = substr($username !== '' ? $username : 'admin', 0, 24);
}

if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
    fwrite(STDERR, "Username must be 3-30 characters: letters, numbers, dot, underscore, or hyphen.\n");
    exit(1);
}

if (strlen($name) < 3) {
    fwrite(STDERR, "Name must be at least 3 characters.\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "A valid email address is required.\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

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
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username AND email <> :email LIMIT 1");
    $stmt->execute([':username' => $username, ':email' => $email]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        $username = substr($username, 0, 24) . random_int(100, 999);
        echo "Requested username already exists; using @{$username} instead.\n";
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    if ($existing) {
        $stmt = $pdo->prepare(
            "UPDATE users
                SET role = 'admin', name = :name, username = :username,
                    password = :password, is_active = 1
              WHERE id = :id"
        );
        $stmt->execute([
            ':name' => $name,
            ':username' => $username,
            ':password' => $passwordHash,
            ':id' => (int) $existing['id'],
        ]);
        echo "Existing account promoted to admin: {$email} (username: {$username})\n";
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO users (role, name, email, username, password, phone, is_active)
             VALUES ('admin', :name, :email, :username, :password, NULL, 1)"
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':username' => $username,
            ':password' => $passwordHash,
        ]);
        echo "Administrator created: {$email} (username: {$username})\n";
    }
} catch (PDOException $e) {
    fwrite(STDERR, "Could not create administrator: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Login at " . BASEURL . "/auth/login and change the password after first login.\n";
