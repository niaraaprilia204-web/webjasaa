<?php

require_once __DIR__ . '/BaseService.php';

/**
 * System Health & Overview Service
 */
class SystemHealthService extends BaseService
{
    public function getSystemHealthOverview()
    {
        $db = new Database();
        
        $dbConnected = $db->isConnected();
        $dbError = $db->getError();

        $servicesScope = [
            [
                'title' => 'Instalasi & Maintenance CCTV',
                'icon'  => 'bi-camera-video',
                'desc'  => 'Pemasangan IP Camera, DVR/NVR, setting online remote hp & pemeliharaan.'
            ],
            [
                'title' => 'Konfigurasi Wi-Fi & Mikrotik',
                'icon'  => 'bi-wifi',
                'desc'  => 'Setting routerboard, Bandwidth Management, Hotspot Voucher & Access Point.'
            ],
            [
                'title' => 'Service PC, Laptop & OS',
                'icon'  => 'bi-pc-display',
                'desc'  => 'Instalasi OS, hardware upgrade SSD/RAM, maintenance & pembersihan virus.'
            ],
            [
                'title' => 'Service Printer & Infus',
                'icon'  => 'bi-printer',
                'desc'  => 'Service printer inkjet/laserjet, pasang sistem infus & reset waste ink.'
            ],
            [
                'title' => 'Troubleshooting LAN & Kabel',
                'icon'  => 'bi-diagram-3',
                'desc'  => 'Crimping kabel UTP/FTP, instalasi socket wall, patch panel & testing LAN.'
            ],
            [
                'title' => 'Konsultasi IT & Maintenance',
                'icon'  => 'bi-cpu',
                'desc'  => 'Jasa pemeliharaan rutin lab sekolah, kantor, serta konsultasi jaringan.'
            ]
        ];

        $rolesScope = [
            [
                'name'  => 'Customer',
                'badge' => 'Pengaju Jasa',
                'color' => 'primary',
                'icon'  => 'bi-person-heart',
                'desc'  => 'Masyarakat & Civitas Sekolah yang membuat pesanan & memantau tiket.'
            ],
            [
                'name'  => 'Teknisi (Siswa)',
                'badge' => 'Eksekutor',
                'color' => 'success',
                'icon'  => 'bi-tools',
                'desc'  => 'Siswa TKJ yang memproses pekerjaan teknis & mengupdate progress.'
            ],
            [
                'name'  => 'Operator',
                'badge' => 'Manager Unit',
                'color' => 'warning',
                'icon'  => 'bi-person-gear',
                'desc'  => 'Mengatur penugasan siswa, verifikasi tiket & kelola stok sparepart.'
            ],
            [
                'name'  => 'Kepala Sekolah',
                'badge' => 'Evaluator',
                'color' => 'danger',
                'icon'  => 'bi-briefcase',
                'desc'  => 'Dashboard pengawasan omset, kinerja unit bisnis & jam kerja siswa.'
            ]
        ];

        return $this->response(true, 'Data status sistem berhasil dimuat', [
            'dbConnected'    => $dbConnected,
            'dbError'        => $dbError,
            'dbName'         => DB_NAME,
            'dbHost'         => DB_HOST,
            'phpVersion'     => PHP_VERSION,
            'services'       => $servicesScope,
            'roles'          => $rolesScope
        ]);
    }
}
