# Notifikasi WhatsApp TKJ Service Hub (Fonnte)

Dokumen ini menjelaskan konfigurasi, alur, keamanan, dan pengujian fitur
notifikasi WhatsApp untuk Admin dan Customer.

---

## 1. Ringkasan

| Aspek | Keterangan |
|---|---|
| Provider | Fonnte (WhatsApp gateway) |
| Endpoint | `POST https://api.fonnte.com/send` |
| Header | `Authorization: <FONNTE_TOKEN>` (tanpa `Bearer`) |
| Penerima Admin | `ADMIN_WHATSAPP_NUMBER` dari `.env` |
| Penerima Customer | kolom `users.phone` milik akun tersebut (dari database) |
| Halaman Admin | `/admin/whatsapp` (status + riwayat + kirim ulang) |
| Halaman Admin | `/admin/customer-reports` (kelola laporan customer) |
| Halaman Customer | `/customer/reports` dan `/customer/reports/create` |

Dokumentasi resmi Fonnte: <https://docs.fonnte.com/api-send-message/>

## 2. Konfigurasi (server-side)

Semua nilai dibaca dari `.env` di root project:

```env
# Token Fonnte (Fonnte Dashboard -> Device). Server-side only.
FONNTE_TOKEN=

# Nomor WhatsApp tujuan notifikasi Admin.
ADMIN_WHATSAPP_NUMBER=

# Timeout request Fonnte dalam detik (5-120).
FONNTE_TIMEOUT_SECONDS=15
```

- `FONNTE_TOKEN` juga dipakai fitur OTP; `FONNTE_API_TOKEN` tetap diterima
  sebagai nama lama (fallback).
- `.env` sudah ada di `.gitignore` (bersama `chatbot-node/.env`).
- Token **tidak pernah** tampil di HTML, JavaScript, log, atau respons API.

## 3. Pemetaan Aktivitas ↔ Notifikasi

| Aktivitas | Penerima | Isi pesan |
|---|---|---|
| Customer mendaftar | Admin | Nama, email, ID customer, waktu pendaftaran |
| Customer membuat laporan | Admin | Kode laporan, nama customer, judul, deskripsi singkat, tanggal, status |
| Customer membuat laporan | Customer pemilik | Aktivitas, kode laporan, status berhasil, tindak lanjut |
| Laporan gagal disimpan | Customer pemilik | Aktivitas, status **Gagal**, keterangan, tindak lanjut (tanpa konfirmasi sukses) |
| Customer membuat pesanan | Admin | Kode pesanan, nama customer, layanan, harga (DB), tanggal, status |
| Customer membuat pesanan | Customer pemilik | Aktivitas, kode pesanan, status berhasil, tindak lanjut |
| Pesanan gagal disimpan | Customer pemilik | Aktivitas, status **Gagal**, keterangan, tindak lanjut |
| Admin ubah status laporan | Customer pemilik | Kode laporan, status lama → baru, catatan admin |
| Admin ubah status pesanan | Customer pemilik | Kode pesanan, status lama → baru |

Customer **tidak** menerima notifikasi tambahan saat pendaftaran (hanya Admin).

## 4. Dua Status yang Dipisahkan

| Kolom | Nilai | Arti |
|---|---|---|
| `business_status` | `success` / `failed` | Hasil proses bisnis (data tersimpan atau gagal) |
| `status` | `pending` / `sent` / `failed` | Hasil pengiriman WhatsApp |

Aturan: notifikasi sukses hanya dikirim setelah transaksi database berhasil.
Kegagalan Fonnte **tidak** membatalkan transaksi bisnis yang sudah berhasil.

## 5. Idempotency (anti notifikasi ganda)

Tabel `whatsapp_notification_logs` memakai kolom `idempotency_key` dengan
kunci unik:

```
idempotency_key = SHA1( event | referensi | peran penerima | nomor penerima )
```

- Notifikasi ke **Admin** dan ke **Customer** untuk entitas yang sama tetap
  sama-sama terkirim (peran & nomor berbeda → kunci berbeda).
- Pengiriman ulang ke penerima yang sama untuk event yang sama **dihindari**
  (hasil: `duplicate_skipped`).

> Catatan migrasi: constraint lama `UNIQUE (event, reference_id)` terlalu
> ketat karena memblokir notifikasi kedua untuk entitas yang sama. Constraint
> tersebut dilepas pada migrasi `009_relax_notification_unique_key.sql` dan
> digantikan `idempotency_key`.

## 6. Pengambilan Nomor WhatsApp

| Penerima | Sumber | Normalisasi |
|---|---|---|
| Admin | `ADMIN_WHATSAPP_NUMBER` (`.env`) | `08…` → `62…`, `+62 …` → `62…` |
| Customer | `users.phone` (database) | `OtpService::normalizePhone()` → `628xxxxxxxxx` |

Nomor **tidak pernah** diambil dari input frontend. Tidak ada hidden field
penerima. Nomor customer diambil dari baris `users` milik akun tersebut,
sehingga customer hanya menerima notifikasi miliknya sendiri.

Nomor mentah **tidak disimpan** di tabel log (hanya versi disamarkan).
Saat fitur kirim ulang dijalankan, nomor di-resolve ulang dari sumber resmi.

## 7. Struktur Log Notifikasi

Tabel: `whatsapp_notification_logs`

| Kolom | Fungsi |
|---|---|
| `event` | `customer_registered`, `order_created`, `report_created`, `order_status_updated`, `report_status_updated`, `order_failed`, `report_failed` |
| `activity_type` | `registration`, `order`, `report`, `status_update` |
| `reference_id` | `customer-{id}`, `order-{id}`, `report-{id}`, `user-{id}-{YYYYMMDDHH}` |
| `idempotency_key` | Kunci unik anti-duplikasi (UNIQUE) |
| `recipient` | Nomor tujuan **disamarkan** (`6281*****7890`) |
| `recipient_name` | Nama penerima (tampil di dashboard) |
| `recipient_user_id` | Referensi ke `users.id` (untuk resolve ulang nomor saat kirim ulang) |
| `status` | Status pengiriman: `pending` / `sent` / `failed` |
| `business_status` | Status proses bisnis: `success` / `failed` |
| `message_preview` / `message_body` | Ringkasan & isi pesan (untuk kirim ulang) |
| `error_message`, `http_code`, `provider_message_id` | Bukti hasil pengiriman |
| `attempts`, `sent_at`, `created_at`, `updated_at` | Riwayat pengiriman |

## 8. Halaman Admin

### `/admin/whatsapp`
- Status koneksi Fonnte + status token (tanpa menampilkan token).
- Nomor WhatsApp Admin (disamarkan).
- Ringkasan per jenis aktivitas (terkirim/gagal).
- Tabel riwayat: waktu, jenis aktivitas, penerima (nama + nomor disamarkan),
  referensi, status proses bisnis, status pengiriman, waktu kirim, error.
- Tombol **kirim ulang** (hanya untuk status `failed`; pesan yang sudah
  terkirim tidak dapat dikirim ulang).

### `/admin/customer-reports`
- Daftar seluruh laporan customer + filter status.
- Ubah status laporan (dengan catatan admin). Perubahan status otomatis
  memberi tahu pemilik laporan via WhatsApp.

## 9. Keamanan

- Semua pengiriman lewat backend PHP; tidak ada panggilan Fonnte dari browser.
- Token hanya dari `.env`; tidak pernah di frontend/HTML/JS/log.
- CSRF pada seluruh form (token `admin_csrf` untuk admin, `report` untuk
  customer).
- Otorisasi backend: halaman admin dibatasi `RoleMiddleware(['admin'])`;
  laporan customer hanya menampilkan milik akun yang login (`user_id` dari
  session).
- Customer tidak dapat memicu kirim ulang notifikasi.
- Prepared statement untuk seluruh query.
- Pesan tidak memuat password, OTP, atau token.

## 10. Pengujian

```powershell
cd C:\laragon\www\webjasa

# Uji logika integrasi lengkap (65 pemeriksaan)
php tests\whatsapp_notification_flows_test.php

# Uji notifikasi dasar (45 pemeriksaan)
php tests\whatsapp_notification_test.php

# Uji end-to-end HTTP/browser (28 pemeriksaan)
php tests\test_whatsapp_http_e2e.php
```

Pengujian memakai **transport simulasi** yang disuntikkan ke `FonnteService`
(`new FonnteService($token, $endpoint, $transport)`), sehingga tidak ada
permintaan jaringan dan tidak ada token palsu di `.env`. Yang terbukti:
pemicu, idempotency, logging, pemisahan status, penanganan error, sumber
nomor penerima, isolasi antar customer, dan keamanan pesan.

Pengiriman WhatsApp **nyata** hanya dapat dibuktikan setelah `FONNTE_TOKEN`
asli diisi: log di `/admin/whatsapp` akan menampilkan `status = sent`,
`http_code = 200`, dan `provider_message_id` dari Fonnte.

## 11. Pengujian Nyata (Manual)

1. Isi `FONNTE_TOKEN` + `ADMIN_WHATSAPP_NUMBER` di `.env`, restart Apache/PHP.
2. Pastikan device Fonnte di dashboard **terhubung** (connected).
3. Login Admin → `/admin/whatsapp` → pastikan status "Terhubung".
4. Daftar customer baru (atau login customer yang nomornya valid) →
   buat pesanan / kirim laporan.
5. Periksa nomor WhatsApp **Admin** menerima notifikasi.
6. Periksa nomor WhatsApp **customer** menerima konfirmasi.
7. Di `/admin/whatsapp`, pastikan baris terbaru berstatus `sent`.
8. Verifikasi juga langsung di dashboard Fonnte (riwayat pesan) dan di
   perangkat WhatsApp penerima.
