<?php

require_once __DIR__ . '/../../app/Config/Config.php';

class DatabaseSeeder
{
    private $pdo;

    public function __construct()
    {
        try {
            $this->pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Seeder DB Connection Failed: " . $e->getMessage() . "\n");
        }
    }

    public function run()
    {
        echo "=== TKJ Service Hub - Seeding Demo Data ===\n";

        // 1. Seed Service Categories
        $categories = [
            ['name' => 'Instalasi & Perangkat', 'slug' => 'instalasi-perangkat', 'description' => 'Jasa pemasangan CCTV dan jaringan fisik', 'icon' => 'bi-camera-video'],
            ['name' => 'Perbaikan Hardware & Laptop', 'slug' => 'perbaikan-hardware', 'description' => 'Service PC, laptop, dan printer', 'icon' => 'bi-pc-display'],
            ['name' => 'Jaringan & Software', 'slug' => 'jaringan-software', 'description' => 'Konfigurasi Mikrotik, Wi-Fi, dan instalasi aplikasi', 'icon' => 'bi-wifi'],
            ['name' => 'Maintenance & Konsultasi', 'slug' => 'maintenance-konsultasi', 'description' => 'Pemeliharaan berkala dan konsultasi IT', 'icon' => 'bi-cpu']
        ];

        $stmtCat = $this->pdo->prepare("INSERT IGNORE INTO `service_categories` (`name`, `slug`, `description`, `icon`) VALUES (:name, :slug, :description, :icon)");
        foreach ($categories as $cat) {
            $stmtCat->execute($cat);
        }
        echo " - Service Categories seeded.\n";

        // 2. Seed 10 Sample Services
        $services = [
            [1, 'Instalasi CCTV', 'instalasi-cctv', 'Pemasangan IP Camera/DVR, penataan kabel, dan setting online remote HP.', '1 hari', 'Rp 150.000 - Rp 500.000'],
            [1, 'Instalasi Jaringan LAN', 'instalasi-jaringan-lan', 'Crimping kabel UTP/FTP, penataan rack server, patch panel, dan testing LAN.', '3-5 jam', 'Rp 50.000 - Rp 250.000'],
            [3, 'Konfigurasi Wi-Fi', 'konfigurasi-wi-fi', 'Setting Access Point, Mikrotik Routerboard, Bandwidth Management, & Hotspot.', '2-4 jam', 'Rp 75.000 - Rp 300.000'],
            [2, 'Service Komputer', 'service-komputer', 'Diagnosa mati total, ganti power supply, pembersihan dust/thermal paste, & upgrade PC.', '1-2 hari', 'Rp 50.000 - Rp 200.000'],
            [2, 'Service Laptop', 'service-laptop', 'Pembersihan fan cooling, perbaikan keyboard, engsel, ganti LCD, & upgrade SSD.', '1-3 hari', 'Rp 75.000 - Rp 350.000'],
            [3, 'Instalasi Software', 'instalasi-software', 'Instalasi OS Windows/Linux, Driver, Microsoft Office, & Aplikasi Produktivitas.', '1-2 jam', 'Rp 35.000 - Rp 100.000'],
            [4, 'Maintenance Komputer', 'maintenance-komputer', 'Pemeriksaan berkala hardware & software lab komputer sekolah atau kantor.', '1 hari', 'Rp 100.000 - Rp 400.000'],
            [3, 'Troubleshooting Jaringan', 'troubleshooting-jaringan', 'Diagnosa koneksi internet putus-nyambung, RTO, konflik IP, & router hang.', '1-3 jam', 'Rp 50.000 - Rp 150.000'],
            [2, 'Service Printer', 'service-printer', 'Pembersihan head printer, reset waste ink absorber, perbaikan paper jam, & pasang infus.', '1 hari', 'Rp 50.000 - Rp 175.000'],
            [4, 'Jasa IT Lainnya', 'jasa-it-lainnya', 'Konsultasi teknis, backup data, perapihan kabel, & dukungan event sekolah.', 'Tentatif', 'Rp 50.000 - Rp 300.000']
        ];

        $stmtSvc = $this->pdo->prepare("INSERT IGNORE INTO `services` (`category_id`, `title`, `slug`, `description`, `estimated_duration`, `price_range`) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($services as $svc) {
            $stmtSvc->execute($svc);
        }
        echo " - 10 Sample Services seeded.\n";

        // 3. Seed Demo Users & Passwords (password: 'password123')
        $hashedPass = password_hash('password123', PASSWORD_BCRYPT);
        
        $users = [
            ['customer', 'Budi Santoso', 'customer@gmail.com', $hashedPass, '081299887766'],
            ['technician', 'Rizky Pratama (Siswa TKJ)', 'rizky.tkj@school.id', $hashedPass, '085711223344'],
            ['operator', 'Pak Ahmad (Operator TKJ)', 'operator@school.id', $hashedPass, '081344556677'],
            ['principal', 'Drs. H. Mulyadi, M.Pd (Kepala Sekolah)', 'kepsek@school.id', $hashedPass, '081122334455']
        ];

        $stmtUser = $this->pdo->prepare("INSERT IGNORE INTO `users` (`role`, `name`, `email`, `password`, `phone`) VALUES (?, ?, ?, ?, ?)");
        foreach ($users as $u) {
            $stmtUser->execute($u);
        }
        echo " - Demo Users for 4 roles seeded.\n";

        // Fetch User IDs
        $customerUserId = $this->pdo->query("SELECT id FROM users WHERE email='customer@gmail.com'")->fetchColumn();
        $technicianUserId = $this->pdo->query("SELECT id FROM users WHERE email='rizky.tkj@school.id'")->fetchColumn();

        // 4. Seed Customer Profile
        if ($customerUserId) {
            $this->pdo->exec("INSERT IGNORE INTO `customers` (`user_id`, `address`, `institution`) VALUES ('{$customerUserId}', 'Jl. Merdeka No. 45, RT 02/RW 05', 'Masyarakat Umum')");
            echo " - Customer profile seeded.\n";
        }

        // 5. Seed Technician Profile
        if ($technicianUserId) {
            $this->pdo->exec("INSERT IGNORE INTO `technicians` (`user_id`, `nisn`, `class_grade`, `skills_summary`, `status`) VALUES ('{$technicianUserId}', '0051234567', 'XII TKJ 1', 'Instalasi CCTV, Mikrotik Routerboard, Crimping LAN, Hardware PC', 'available')");
            echo " - Technician profile seeded.\n";
        }

        // 6. Seed Sample Order
        $customerId = $this->pdo->query("SELECT id FROM customers WHERE user_id='{$customerUserId}'")->fetchColumn();
        $serviceCctvId = $this->pdo->query("SELECT id FROM services WHERE slug='instalasi-cctv'")->fetchColumn();

        if ($customerId && $serviceCctvId) {
            $orderCode = 'ORD-' . date('Ymd') . '-0001';
            $stmtOrd = $this->pdo->prepare("INSERT IGNORE INTO `orders` (`order_code`, `customer_id`, `service_id`, `status`, `problem_description`, `device_info`, `location_address`, `scheduled_date`) VALUES (?, ?, ?, 'assigned', ?, ?, ?, ?)");
            $stmtOrd->execute([
                $orderCode,
                $customerId,
                $serviceCctvId,
                'Permintaan instalasi 4 unit IP Camera Hikvision outdoor beserta setting monitoring HP.',
                'Kit CCTV 4 Cam Hikvision 2MP',
                'Ruang Perpustakaan & Halaman Depan Sekolah',
                date('Y-m-d H:i:s', strtotime('+1 day'))
            ]);
            echo " - Sample Order seeded (Code: {$orderCode}).\n";

            $orderId = $this->pdo->query("SELECT id FROM orders WHERE order_code='{$orderCode}'")->fetchColumn();
            $technicianId = $this->pdo->query("SELECT id FROM technicians WHERE user_id='{$technicianUserId}'")->fetchColumn();

            if ($orderId && $technicianId) {
                // 7. Seed Work Order
                $this->pdo->exec("INSERT IGNORE INTO `work_orders` (`order_id`, `technician_id`, `status`, `diagnosis_notes`) VALUES ('{$orderId}', '{$technicianId}', 'assigned', 'Jadwal survei fisik lokasi dan penarikan kabel coaxial/UTP')");
                echo " - Sample Work Order assigned to technician.\n";
            }
        }

        echo "All demo data seeded successfully!\n";
        return true;
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $seeder = new DatabaseSeeder();
    $seeder->run();
}
