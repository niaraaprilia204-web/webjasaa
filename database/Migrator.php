<?php

require_once __DIR__ . '/../app/Config/Config.php';

class Migrator
{
    private $pdo;

    public function __construct()
    {
        try {
            $this->pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT, DB_USER, DB_PASS);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Migration DB Connection Failed: " . $e->getMessage() . "\n");
        }
    }

    public function run()
    {
        echo "=== TKJ Service Hub - Running Database Migrations ===\n";

        $migrationFiles = glob(__DIR__ . '/migrations/*.sql');
        sort($migrationFiles);

        foreach ($migrationFiles as $file) {
            $filename = basename($file);
            echo "Executing migration: {$filename} ... ";
            $sql = file_get_contents($file);

            try {
                $this->pdo->exec($sql);
                echo "[SUCCESS]\n";
            } catch (PDOException $e) {
                echo "[FAILED]\nError: " . $e->getMessage() . "\n";
                return false;
            }
        }

        echo "All migrations completed successfully!\n";
        return true;
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $migrator = new Migrator();
    $migrator->run();
}
