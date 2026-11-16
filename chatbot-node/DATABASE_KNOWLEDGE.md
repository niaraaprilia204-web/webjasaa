# TKJ Service Hub - Database Knowledge (Internal)

Dokumen ini berisi struktur database asli dari project TKJ Service Hub.
Digunakan oleh Node.js chatbot untuk memahami fungsi tabel dan relasi
saat menjawab pertanyaan admin tentang data.

Sumber: `SHOW CREATE TABLE` dari database `tkj_service_hub` (MySQL).
Tidak ada tabel atau kolom yang diarang.

## 1. Tabel: users

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | int PK AI | Primary key user |
| role | enum('customer','technician','operator','principal','admin') | Role user (default customer) |
| name | varchar(100) | Nama lengkap |
| email | varchar(100) UNIQUE | Email login |
| username | varchar(50) UNIQUE | Username login |
| password | varchar(255) | Hash password (bcrypt) |
| phone | varchar(20) | Nomor telepon |
| phone_verified_at | datetime | Waktu verifikasi OTP |
| avatar | varchar(255) | Path foto profil |
| is_active | tinyint(1) | Status aktif (1=aktif) |
| created_at | timestamp | Waktu dibuat |
| updated_at | timestamp | Waktu update |

**Fungsi:** Tabel akun pengguna inti. Semua role (customer, admin, technician, operator, principal) tersimpan di sini.

## 2. Tabel: customers

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | int PK AI | Primary key customer |
| user_id | int FK → users.id | Referensi ke users (UNIQUE) |
| address | text | Alamat customer |
| institution | varchar(100) | Institusi/sekolah |
| notes | text | Catatan tambahan |
| created_at | timestamp | Waktu dibuat |
| updated_at | timestamp | Waktu update |

**Relasi:** `customers.user_id → users.id` (ON DELETE CASCADE)

**Fungsi:** Profil tambahan untuk user dengan role customer.

## 3. Tabel: orders

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | int PK AI | Primary key pesanan |
| order_code | varchar(30) UNIQUE | Kode pesanan (contoh: ORD-20261103-5400) |
| customer_id | int FK → customers.id | Pelanggan pemesan |
| service_id | int FK → services.id | Layanan yang dipesan |
| status | enum('submitted','verified','scheduled','assigned','in_progress','waiting_confirmation','completed','cancelled') | Status pesanan |
| payment_status | enum('unpaid','pending','paid','refunded') | Status pembayaran |
| payment_method | varchar(50) | Metode pembayaran |
| payment_amount | decimal(12,2) | Jumlah pembayaran |
| paid_at | datetime | Waktu pembayaran |
| problem_description | text | Deskripsi masalah pelanggan |
| device_info | varchar(255) | Info perangkat |
| location_address | text | Alamat lokasi service |
| scheduled_date | datetime | Jadwal kunjungan |
| completed_at | datetime | Waktu selesai |
| operator_notes | text | Catatan operator |
| created_at | timestamp | Waktu pesanan dibuat |
| updated_at | timestamp | Waktu update |

**Relasi:**
- `orders.customer_id → customers.id` (ON DELETE RESTRICT)
- `orders.service_id → services.id` (ON DELETE RESTRICT)

**Fungsi:** Tabel transaksi pesanan layanan inti.

## 4. Tabel: services

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | int PK AI | Primary key layanan |
| category_id | int FK → service_categories.id | Kategori layanan |
| title | varchar(150) | Nama layanan |
| slug | varchar(150) UNIQUE | URL-friendly slug |
| description | text | Deskripsi layanan |
| estimated_duration | varchar(50) | Estimasi waktu pengerjaan |
| price_range | varchar(100) | Rentang biaya (contoh: Rp 150.000 - Rp 500.000) |
| is_active | tinyint(1) | Layanan aktif atau tidak |
| created_at | timestamp | Waktu dibuat |
| updated_at | timestamp | Waktu update |

**Relasi:** `services.category_id → service_categories.id` (ON DELETE CASCADE)

**Fungsi:** Katalog layanan yang ditawarkan TKJ Service Hub.

## 5. Tabel: service_categories

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | int PK AI | Primary key kategori |
| name | varchar(100) UNIQUE | Nama kategori |
| slug | varchar(100) UNIQUE | Slug kategori |
| description | text | Deskripsi kategori |
| icon | varchar(50) | Icon Bootstrap (default bi-tools) |
| created_at | timestamp | Waktu dibuat |
| updated_at | timestamp | Waktu update |

**Fungsi:** Kelompokisasi layanan.

## 6. Tabel: work_orders

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | int PK AI | Primary key work order |
| order_id | int FK → orders.id | Referensi pesanan |
| technician_id | int FK → technicians.id | Teknisi yang ditugaskan |
| status | enum('assigned','accepted','in_progress','done') | Status pengerjaan |
| diagnosis_notes | text | Catatan diagnosa teknisi |
| action_taken | text | Tindakan yang dilakukan |
| parts_used | text | Sparepart yang digunakan |
| assigned_at | datetime | Waktu ditugaskan |
| finished_at | datetime | Waktu selesai |
| created_at | timestamp | Waktu dibuat |
| updated_at | timestamp | Waktu update |

**Relasi:**
- `work_orders.order_id → orders.id` (ON DELETE CASCADE)
- `work_orders.technician_id → technicians.id` (ON DELETE RESTRICT)

**Fungsi:** Catatan pengerjaan teknisi terhadap setiap pesanan.

## 7. Tabel: technicians

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | int PK AI | Primary key teknisi |
| user_id | int FK → users.id (UNIQUE) | Referensi ke users |
| nisn | varchar(20) UNIQUE | NISN siswa |
| class_grade | varchar(30) | Kelas/tingkat |
| skills_summary | text | Ringkasan keterampilan |
| status | enum('available','on_duty','off') | Status ketersediaan |
| created_at | timestamp | Waktu dibuat |
| updated_at | timestamp | Waktu update |

**Relasi:** `technicians.user_id → users.id` (ON DELETE CASCADE)

**Fungsi:** Profil teknisi siswa TKJ.

## 8. Tabel: otp_verifications

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint PK AI | Primary key OTP |
| user_id | int FK → users.id | User yang terkait (nullable) |
| phone | varchar(20) | Nomor telepon tujuan |
| purpose | varchar(30) | Tujuan OTP (default phone_verification) |
| otp_hash | varchar(255) | Hash kode OTP |
| attempts | tinyint unsigned | Jumlah percobaan |
| max_attempts | tinyint unsigned | Maksimal percobaan (default 5) |
| request_ip | varchar(45) | IP peminta |
| provider_message_id | varchar(100) | ID pesan provider WhatsApp |
| expires_at | datetime | Waktu kedaluwarsa |
| consumed_at | datetime | Waktu digunakan |
| created_at | datetime | Waktu dibuat |

**Relasi:** `otp_verifications.user_id → users.id` (ON DELETE SET NULL)

**Fungsi:** Verifikasi nomor telepon melalui OTP WhatsApp.

## 9. Tabel: notifications

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | int PK AI | Primary key notifikasi |
| user_id | int FK → users.id | Penerima notifikasi |
| title | varchar(150) | Judul notifikasi |
| message | text | Isi pesan |
| link | varchar(255) | Tautan tindakan |
| is_read | tinyint(1) | Status dibaca (default 0) |
| created_at | timestamp | Waktu dibuat |

**Relasi:** `notifications.user_id → users.id` (ON DELETE CASCADE)

**Fungsi:** Sistem notifikasi dalam aplikasi.

## 10. Tabel: ai_chat_logs

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint PK AI | Primary key log |
| user_id | int | User yang berchat (nullable) |
| session_id | varchar(128) | ID sesi chat |
| query | text | Pertanyaan pengguna |
| reply | mediumtext | Balasan AI |
| source | varchar(40) | Sumber balasan (gemini_api, local_engine, openrouter, dll.) |
| category | varchar(80) | Kategori pertanyaan |
| ip_address | varchar(45) | IP pengguna |
| created_at | datetime | Waktu chat |

**Fungsi:** Log percakapan chatbot untuk review admin/operator.

## 11. Tabel: app_settings

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | int PK AI | Primary key |
| setting_key | varchar(80) UNIQUE | Kunci pengaturan |
| setting_value | text | Nilai pengaturan |
| updated_at | datetime | Waktu update |

**Fungsi:** Pengaturan aplikasi (misal: AI API enabled, model, temperature, extra_instruction).

## 12. Tabel: order_files

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | int PK AI | Primary key file |
| order_id | int FK → orders.id | Referensi pesanan |
| file_path | varchar(255) | Path file yang diunggah |
| file_type | varchar(50) | Tipe file (default image) |
| caption | varchar(255) | Keterangan file |
| uploaded_at | datetime | Waktu unggah |

**Relasi:** `order_files.order_id → orders.id` (ON DELETE CASCADE)

**Fungsi:** File lampiran pesanan (foto perangkat, dll.).

## 13. Tabel: reviews

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | int PK AI | Primary key ulasan |
| order_id | int FK → orders.id (UNIQUE) | Referensi pesanan |
| customer_id | int FK → customers.id | Pelanggan yang memberi ulasan |
| rating | tinyint | Rating 1-5 (CHECK constraint) |
| comment | text | Komentar ulasan |
| created_at | timestamp | Waktu dibuat |

**Relasi:**
- `reviews.order_id → orders.id` (ON DELETE CASCADE)
- `reviews.customer_id → customers.id` (ON DELETE CASCADE)

**Fungsi:** Ulasan pelanggan terhadap pesanan yang sudah selesai.

## 14. Relasi Antar Tabel (Ringkasan)

```
users (1) ── (1) customers ── (N) orders ── (1) services ── (1) service_categories
   │              │              │
   │              │              └── (1) work_orders ── (1) technicians ── (1) users
   │              │
   │              └── (N) reviews
   │
   └── (N) notifications
   └── (N) otp_verifications
```

## 15. Alur Data Utama

1. **Registrasi:** User → `users` (role=customer) → `customers` (profil tambahan)
2. **Login:** User memasukkan email/username + password → `AuthService` verifikasi → session disimpan dengan role dari `users.role`
3. **Pemesanan:** Customer buat order → `orders` dengan `customer_id` dan `service_id` → `order_files` (jika ada lampiran)
4. **Pembayaran:** Order diberi `payment_status` dan `payment_amount` → `paid_at` saat dibayar
5. **Penugasan:** Operator assign teknisi → `work_orders` dengan `technician_id`
6. **Pengerjaan:** Teknisi update `work_orders` (diagnosis, action, parts) → status berubah
7. **Selesai:** Order status `completed`, `completed_at` terisi
8. **Ulasan:** Customer beri rating → `reviews`

## 16. Endpoint Internal PHP (Read-Only, HMAC Protected)

Semua endpoint di bawah `/api/internal/ai/` memerlukan token HMAC dari Node.js:

| Endpoint | Method | Role | Deskripsi |
|----------|--------|------|-----------|
| `/api/internal/ai/health` | GET | customer, admin | Cek kesehatan layanan internal |
| `/api/internal/ai/context` | GET | customer, admin | Konteks role dan permissions |
| `/api/internal/ai/orders` | GET | customer, admin | Daftar pesanan (customer: milik sendiri; admin: semua) |
| `/api/internal/ai/customer/profile` | GET | customer | Profil pelanggan yang login |
| `/api/internal/ai/customer/orders` | GET | customer | Pesanan milik customer yang login |
| `/api/internal/ai/services` | GET | customer, admin | Katalog layanan aktif |
| `/api/internal/ai/admin/stats` | GET | admin | Statistik admin (total, hari ini, bulan ini, status, pendapatan, dll.) |
| `/api/internal/ai/admin/activity-summary` | GET | admin | Ringkasan aktivitas (7 hari, 30 hari, pembayaran, pesanan terbaru, pelanggan teratas) |

### 16.2 Endpoint retrieval data (Tahap Intelijensi)

Semua di bawah `/api/internal/ai/data/`, controller `AiDataController`.

| Endpoint | Method | Role | Tool Node.js | Sumber query |
|----------|--------|------|--------------|---------------|
| `/api/internal/ai/data/identity` | GET | customer, admin | `get_user_identity` | `users` |
| `/api/internal/ai/data/public-info` | GET | customer, admin | `get_public_info` | `service_categories`, `services` |
| `/api/internal/ai/data/services` | GET | customer, admin | `get_service_list`, `get_service_prices` | `services` + `service_categories` |
| `/api/internal/ai/data/my-orders` | GET | customer, admin | `get_my_orders` | `orders` + `customers` + `services` |
| `/api/internal/ai/data/order-detail` | GET | customer, admin | `get_order_details` | `orders` (+ `work_orders`, `reviews` untuk admin) |
| `/api/internal/ai/data/admin/totals` | GET | admin | `get_totals` | `users`, `customers`, `orders`, `services`, `technicians`, `reviews`, `notifications` |
| `/api/internal/ai/data/admin/customers` | GET | admin | `get_customer_list` | `users` + `customers` + `orders` |
| `/api/internal/ai/data/admin/orders` | GET | admin | `get_orders_by_period` | `orders` + `customers` + `users` + `services` |
| `/api/internal/ai/data/admin/orders-summary` | GET | admin | `get_orders_summary` | `orders`, `services`, `customers`, `work_orders` |
| `/api/internal/ai/data/admin/payments` | GET | admin | `get_payment_summary` | `orders` |
| `/api/internal/ai/data/admin/technicians` | GET | admin | `get_technician_list` | `technicians` + `users` + `work_orders` |
| `/api/internal/ai/data/admin/popular-services` | GET | admin | `get_popular_services` | `services` + `orders` |
| `/api/internal/ai/data/admin/period-comparison` | GET | admin | `get_period_comparison` | `orders` |
| `/api/internal/ai/data/admin/schema` | GET | admin | `get_database_schema` | `information_schema` (live) |
| `/api/internal/ai/data/admin/activity` | GET | admin | `get_activity_report` | `orders`, `users`, `ai_chat_logs`, `notifications` |

### 16.3 Periode yang didukung `get_orders_by_period`

`today`, `yesterday`, `this_week`, `this_month`, `last_month`, `last_7_days`,
`last_30_days`, `all`.

## 17. Audit Schema Live

Struktur di atas diverifikasi ulang terhadap `information_schema` database
`tkj_service_hub` (13 tabel). Endpoint `get_database_schema` membaca struktur
ini secara langsung saat runtime, sehingga jawaban chatbot tentang relasi
tabel selalu berasal dari database yang sedang berjalan, bukan dari dokumen statis.

Relasi terverifikasi (foreign key nyata di database):

```
customers.user_id          -> users.id            (UNIQUE)
orders.customer_id         -> customers.id
orders.service_id          -> services.id
services.category_id       -> service_categories.id
work_orders.order_id       -> orders.id
work_orders.technician_id  -> technicians.id
technicians.user_id        -> users.id            (UNIQUE)
reviews.order_id           -> orders.id           (UNIQUE)
reviews.customer_id        -> customers.id
order_files.order_id       -> orders.id
notifications.user_id      -> users.id
otp_verifications.user_id  -> users.id
```

`ai_chat_logs.user_id` hanya indeks biasa (bukan foreign key).

## 18. Alur Identitas Pengguna

```text
PHP session ($_SESSION['user'])
  -> AiInternalAuthService::issueHandoffToken() menyisipkan name/email/username
  -> token HMAC bertanda tangan (audience = node-chatbot, TTL 30-300 detik)
  -> Node.js internalAuth memverifikasi signature + nonce (anti-replay)
  -> req.aiUser { userId, role, scope, name, email, username }
  -> system prompt OpenRouter memuat blok IDENTITAS
  -> jawaban "Siapa saya?" memakai nama & role dari session
```

Identitas tidak pernah dibaca dari pesan pengguna dan tidak pernah diminta
ulang kepada pengguna. Verifikasi silang ke database tersedia di
`GET /api/internal/ai/data/identity`.

## 19. Aturan Keamanan Data

- Customer hanya bisa melihat data milik sendiri (difilter oleh `user_id` dari token HMAC, bukan dari parameter query).
- Customer yang memanggil endpoint `/api/internal/ai/data/admin/*` mendapat `403` (dicek di backend).
- Admin bisa melihat semua data namun hanya READ (tidak ada INSERT/UPDATE/DELETE).
- Tidak ada endpoint tulis untuk chatbot.
- API Key OpenRouter hanya disimpan di `chatbot-node/.env` (server-side).
- HMAC secret hanya disimpan di `.env` PHP dan `.env` Node.js (server-side).
