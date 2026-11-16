# Verifikasi OTP WhatsApp (Fonnte)

Fitur ini memungkinkan pengguna memverifikasi nomor WhatsApp melalui kode OTP 6 digit.
Kode dikirim melalui **API resmi Fonnte** (`https://api.fonnte.com/send`), bukan simulasi.

> **Status kredensial:** proyek ini belum memiliki `FONNTE_API_TOKEN`. Karena itu
> pengiriman WhatsApp sungguhan **belum dapat diuji** sampai token dan perangkat
> Fonnte yang terhubung tersedia. Tanpa token, halaman menampilkan peringatan dan
> endpoint mengembalikan kegagalan `not_configured` — bukan pesan "terkirim".

## Alur

1. Pengguna membuka `/otp` (bisa dari menu akun, halaman login, atau footer).
2. Pengguna memasukkan nomor WhatsApp format Indonesia (`08...`, `628...`, atau `+62...`).
3. Server menormalkan nomor ke `628xxxxxxxxx`, membuat OTP acak 6 digit dengan
   `random_int()`, menyimpan **bcrypt hash**-nya, lalu mengirim pesan melalui Fonnte.
4. Jika Fonnte menolak/gagal, OTP langsung diinvalidasi dan pengguna melihat pesan gagal.
5. Pengguna memasukkan kode. Jika benar, OTP ditandai terpakai secara atomik.
   Jika pengguna sedang login, `users.phone` dan `users.phone_verified_at` diperbarui.

## Konfigurasi

Salin `.env.example` menjadi `.env` (file `.env` sudah di-ignore oleh Git), lalu isi:

```dotenv
# Gunakan URL HTTPS di produksi.
APP_ENV=local
APP_TIMEZONE=Asia/Jakarta
APP_URL=http://localhost/webjasa
APP_TRUST_PROXY_HEADERS=false

# Provider OTP: fonnte (default) atau meta_cloud.
WHATSAPP_PROVIDER=fonnte
# Token dari Fonnte Dashboard -> Device. Rahasia, server-side saja.
FONNTE_API_TOKEN=
```

> Catatan: parser `.env` proyek ini **tidak** membuang komentar di belakang nilai.
> Tulis komentar pada baris terpisah, bukan setelah `=nilai`.

Jangan pernah menaruh token di HTML, JavaScript, source code, atau log.
`Config.php` hanya membaca nilai dari environment. File `.env` tidak boleh
di-commit atau dibagikan.

### Mendapatkan token Fonnte

1. Buat akun di [Fonnte](https://fonnte.com) dan login ke dashboard.
2. Buat/periksa **Device**, lalu hubungkan nomor WhatsApp dengan memindai QR
   (menu **Device -> Connect / Scan QR**).
3. Pastikan status device **connected** dan paket/kuota pesan mencukupi.
4. Salin token device dari dashboard Fonnte.
5. Isi token tersebut ke `FONNTE_API_TOKEN` di `.env` server. Jangan mengirim token
   melalui chat, email, atau menyimpannya di repository.

Gunakan nomor khusus untuk layanan ini, bukan nomor pribadi utama. Patuhi ketentuan
Fonnte dan WhatsApp yang berlaku.

### Endpoint dan autentikasi Fonnte

- Endpoint: `POST https://api.fonnte.com/send`
- Header: `Authorization: {FONNTE_API_TOKEN}` — **tanpa** awalan `Bearer`
- Body: `target=628...`, `message=...`, `countryCode=62`, `connectOnly=true`

`connectOnly=true` membuat Fonnte menolak permintaan ketika device sedang
terputus, sehingga aplikasi tidak melaporkan "terkirim" saat WhatsApp offline.

### Arti respons sukses

Fonnte mengembalikan `status: true` beserta `id` ketika pesan **diterima dan
diantrikan**. Aplikasi hanya menganggap pengiriman berhasil jika:

- HTTP 2xx,
- `status` bernilai `true` (atau `Status: true`), dan
- ada `id` pesan.

Respons `status: false` (misalnya `token invalid`, `device disconnected`,
`insufficient quota`, `target invalid`) selalu dianggap gagal. Pesan sukses
Fonnte berarti **masuk antrean**, bukan jaminan sudah tampil di perangkat
penerima. Pastikan device tetap terhubung dan periksa **Message History** di
dashboard Fonnte untuk status pengiriman.

## Migrasi database

Jalankan hanya migrasi OTP yang bersifat aditif:

```bash
php database/run_otp_migration.php
```

Atau impor `database/migrations/002_create_otp_verification.sql` lewat phpMyAdmin.

> Jangan menjalankan `database/Migrator.php` untuk perubahan ini. File lama
> `001_create_initial_schema.sql` melakukan `DROP TABLE` pada tabel yang sudah ada.

Migrasi membuat:

- tabel `otp_verifications` (hash OTP, expiry, attempts, IP, status konsumsi),
- kolom `users.phone_verified_at`.

## Kebijakan keamanan

| Aturan | Nilai |
| --- | --- |
| Panjang OTP | 6 digit acak (`random_int`) |
| Penyimpanan | `password_hash(..., PASSWORD_BCRYPT)`, bukan plaintext |
| Masa berlaku | 5 menit |
| Sekali pakai | `consumed_at` + update atomik |
| Permintaan per nomor | maksimal 3 per 15 menit |
| Permintaan per IP | maksimal 30 per 15 menit |
| Percobaan verifikasi | maksimal 5 per kode |
| CSRF | token per sesi + header `X-CSRF-Token` |
| HTTPS | wajib saat `APP_ENV=production` (header proxy hanya dipercaya jika `APP_TRUST_PROXY_HEADERS=true`) |
| Token API | hanya dari environment; tidak pernah dikembalikan ke klien/log |
| Log | hanya kode error dan alasan singkat provider, tanpa token/OTP/nomor |

## Pengujian

### 1. Tes logika (tanpa mengirim WhatsApp)

```bash
php tests/otp_verification_test.php
```

Tes memakai pengirim palsu dan **tidak menghubungi Fonnte**. Yang diuji:
normalisasi nomor, payload Fonnte, parsing respons, hash, expiry, kode salah,
kode kedaluwarsa, kode sekali pakai, batas percobaan, rate limit, kegagalan API,
dan penautan akun.

### 2. Tes HTTP tanpa kredensial

Jalankan server, buka `/otp`, lalu coba kirim OTP. Tanpa token, respons yang benar
adalah `502` dengan `error_code: not_configured` dan pesan bahwa layanan belum
dikonfigurasi. Ini membuktikan aplikasi tidak mengarang status "terkirim".

### 3. Tes pengiriman nyata (setelah token diisi)

1. Pastikan device Fonnte berstatus **connected** dan kuota mencukupi.
2. Isi `FONNTE_API_TOKEN` di `.env`, lalu buka ulang halaman `/otp`.
3. Masukkan **nomor WhatsApp Anda sendiri** (jangan nomor orang lain tanpa izin).
4. Klik **Kirim OTP**.
5. Respons yang benar: pesan sukses dengan nomor tersamarkan, lalu kode 6 digit
   masuk ke WhatsApp.
6. Periksa **Message History** di dashboard Fonnte untuk memastikan pesan masuk
   antrean/terkirim.
7. Masukkan kode di halaman `/otp` dan pastikan verifikasi berhasil. Kode yang
   sama tidak boleh bisa dipakai dua kali.
8. Jika kode tidak masuk, jangan menganggap integrasi berhasil. Periksa bagian
   troubleshooting di bawah.

## Troubleshooting

| Gejala / `error_code` | Penyebab yang perlu dicek |
| --- | --- |
| `not_configured` | `FONNTE_API_TOKEN` kosong atau `.env` belum terbaca. |
| `send_failed` + log `token invalid` | Token salah/kedaluwarsa; salin ulang dari dashboard. |
| `send_failed` + log `device disconnected` | Device Fonnte terputus; pindai QR ulang. |
| `send_failed` + log `insufficient quota` | Kuota/paket Fonnte habis; lakukan top up. |
| `send_failed` + log `target invalid` | Nomor tidak valid atau tidak terdaftar di WhatsApp. |
| `send_failed` + `network_error` | Server tidak dapat menjangkau `api.fonnte.com`; cek DNS/firewall/cURL. |
| Kode tidak masuk padahal respons sukses | Device terputus setelah antrean, nomor salah, atau pesan diblokir; cek Message History. |

Jangan menambahkan token ke pesan error, halaman, atau respons JSON untuk
mempermudah debugging. Token hanya boleh dibaca di server.

## Batasan saat ini

- Fitur ini belum menerima webhook status Fonnte, jadi status `delivered/read`
  tidak ditampilkan di aplikasi. Status tersebut dapat dilihat di dashboard Fonnte.
- Pengiriman bergantung pada device Fonnte yang tetap terhubung dan kuota yang tersedia.
- Fonnte adalah gateway pihak ketiga; ketersediaan dan kebijakan pengiriman
  mengikuti layanan Fonnte.

## Opsi fallback: Meta WhatsApp Cloud API

Kode Meta dipertahankan agar integrasi lama tidak rusak, tetapi **tidak aktif
secara default**. Untuk memakainya, set `WHATSAPP_PROVIDER=meta_cloud` dan isi
`WHATSAPP_API_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, serta template AUTHENTICATION
yang sudah disetujui Meta. Tanpa template, Meta hanya mengizinkan pesan teks di
dalam jendela layanan 24 jam.
