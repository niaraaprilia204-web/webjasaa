# TKJ Service Hub 🛠️💻

**TKJ Service Hub** adalah platform aplikasi web berbasis MVC (Model-View-Controller) yang dirancang untuk mengelola unit bisnis teknis siswa **Teknik Komputer & Jaringan (TKJ)**. Aplikasi ini menghubungkan masyarakat dan lingkungan sekolah dengan berbagai jasa & layanan teknologi informasi profesional yang dikerjakan oleh siswa TKJ di bawah pengawasan instruktur/operator.

---

## 📌 Konsep & Layanan Utama

Unit bisnis TKJ Service Hub menyediakan jasa teknis meliputi:
1. 📹 **Instalasi & Maintenance CCTV** (IP Camera, DVR/NVR, Remote Viewing)
2. 🌐 **Konfigurasi Wi-Fi & Jaringan** (Mikrotik, Access Point, VLAN, Bandwidth Management)
3. 💻 **Service PC, Laptop & OS** (Instalasi ulang, Upgrading Hardware, Cleaning, Virus Removal)
4. 🖨️ **Service Printer & System Infus** (Head Cleaning, Reset Cartridge, Cartridge Infus)
5. 🔌 **Troubleshooting & Network Cabling** (Crimping RJ45, Testing LAN, Patch Panel)
6. 🧠 **Jasa IT & Konsultasi Teknis** (Pemeriksaan rutin perangkat sekolah & publik)

---

## 👥 Aktor & Peran (4 Roles)

| Role | Deskripsi Tugas & Wewenang |
| :--- | :--- |
| **Customer** | Masyarakat umum / Civitas Sekolah yang mengajukan pesanan jasa, memantau status tiket pengerjaan, dan memberikan ulasan. |
| **Teknisi (Siswa TKJ)** | Siswa yang menerima penugasan tiket, melakukan diagnosa fisik/perangkat, memperbarui status pengerjaan, dan mencatat penggunaan sparepart. |
| **Operator** | Admin unit bisnis yang memverifikasi tiket masuk, menentukan jadwal & alokasi teknisi siswa, mengelola inventaris sparepart, dan rekap penagihan. |
| **Kepala Sekolah** | Eksekutif/Pimpinan yang memantau dashboard performa unit bisnis, laporan omset/keuangan jasa, serta akumulasi jam kerja teknis siswa. |

---

## 🏗️ Arsitektur MVC & Struktur Folder

Proyek ini dibangun menggunakan **Clean Native PHP MVC Framework** berkinerja tinggi tanpa dependensi eksternal yang rumit, terstruktur secara modular dan *scalable*:

```
webjasa/
├── app/
│   ├── Config/
│   │   └── Config.php          # Konfigurasi URL, App Name, dan Credentials DB
│   ├── Controllers/
│   │   ├── Home.php            # Default Landing Page Controller
│   │   └── NotFound.php        # 404 Error Controller
│   ├── Core/
│   │   ├── App.php             # Core Router (Parsing URL & Request Dispatcher)
│   │   ├── Controller.php      # Base Controller (Render view & load model)
│   │   ├── Database.php        # PDO MySQL Wrapper (Prepared statements & error handling)
│   │   └── Flasher.php         # Session Flash Message Helper
│   ├── Models/                 # Directory untuk Model Data (Bisnis fitur mendatang)
│   └── Views/
│       ├── errors/
│       │   └── 404.php         # View 404 Page Not Found
│       ├── home/
│       │   └── index.php       # Landing Page Dashboard View
│       └── layouts/
│           ├── header.php      # HTML Head, Bootstrap 5 CSS & Navbar
│           └── footer.php      # Footer & Bootstrap 5 JS Bundle
├── database/
│   └── schema.sql              # Blueprint database MySQL/MariaDB & tabel roles
├── public/
│   ├── css/
│   │   └── style.css           # Styling kustom & estetika UI
│   ├── js/
│   │   └── main.js            # JavaScript kustom aplikasi
│   ├── .htaccess               # Asset & Clean URL rewrite rules
│   └── index.php               # Front Controller (Single Entry Point)
├── .htaccess                   # Root Apache rewrite ke folder public/
├── .gitignore                  # Ignoring temp & config local
└── README.md                   # Dokumentasi Resmi Proyek
```

---

## 🚀 Panduan Setup Environment & Quickstart

### Requirement Sistem
- **Web Server**: Apache (XAMPP / WAMP / Laragon)
- **PHP**: Version 8.0 atau lebih baru (Rekomendasi PHP 8.2+)
- **Database**: MySQL / MariaDB

### Langkah Instalasi
1. Pastikan folder proyek terletak di direktori web server XAMPP:
   `C:\xampp\htdocs\webjasa`
2. Jalankan modul **Apache** dan **MySQL** dari **XAMPP Control Panel**.
3. Import atau jalankan file SQL database:
   File lokasi: `database/schema.sql`
   Database Name: `tkj_service_hub`
4. Buka browser dan akses alamat aplikasi:
   [http://localhost/webjasa](http://localhost/webjasa)

---

## 🔌 Rencana Integrasi AI API (Future Expansion)

Arsitektur backend disiapkan untuk menerima integrasi API AI di masa mendatang (misal: rekomendasi diagnosa teknis otomatis, estimasi biaya berbasis deskripsi kendala, atau chatbot customer service) melalui endpoint Controller terpisah.

---

## 📝 Status Inisialisasi Proyek

- [x] Struktur folder MVC scalable dibuat.
- [x] Routing `App.php` & Front Controller `public/index.php` siap.
- [x] Wrapper Database `PDO` dengan *safe error handling* aktif.
- [x] Bootstrap 5 + Font + Custom Style terintegrasi.
- [x] Blueprint SQL `schema.sql` disiapkan.
- [x] Verifikasi nomor WhatsApp dengan OTP 6 digit (Fonnte API) — lihat [docs/WHATSAPP_OTP.md](docs/WHATSAPP_OTP.md).
- [ ] *Fitur bisnis & modul autentikasi (akan dibuat pada prompt berikutnya).*
