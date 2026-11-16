# Kontrak API Chatbot AI — PHP ↔ Node.js

Dokumen ini adalah kontrak Tahap 2. Implementasi penuh OpenRouter dan seluruh
tool data menyusul pada tahap berikutnya.

## 1. Arsitektur

```text
Browser (customer/admin)
  │  POST /ai/chat + PHP session + CSRF header
  ▼
PHP (native MVC)
  │  validasi login, role, CSRF
  │  buat handoff token HMAC (aud = node-chatbot)
  ▼
Node.js chatbot (127.0.0.1:3001)
  │  verifikasi token, role, nonce
  │  buat service token HMAC (aud = php-internal)
  ▼
PHP internal read-only API
  │  verifikasi HMAC, role, nonce
  │  SELECT parameterized + scope dari token
  ▼
MySQL
```

Aturan utama:

- Role **tidak pernah** dibaca dari body/query frontend.
- Node.js tidak memiliki kredensial MySQL.
- AI tidak pernah menerima SQL bebas.
- Tidak ada endpoint tulis untuk chatbot.
- API key OpenRouter hanya dibaca Node dari `.env`.
- Internal secret hanya ada di `.env` PHP dan `.env` Node.

## 2. Role dan Scope

| Role | Chat | Data yang boleh dibaca |
|---|---|---|
| `customer` | Ya | Layanan umum + pesanan milik sendiri |
| `admin` | Ya | Data website sesuai kewenangan, read-only |
| `technician` | Tidak | Hanya helper teknisi (bukan chatbot) |
| `operator` | Tidak | Hanya helper teknisi (bukan chatbot) |
| `principal` | Tidak | Tidak ada chatbot |

Chatbot tidak menerima `user_id`, `role`, atau `scope` dari frontend.

## 3. Browser → PHP

### `POST /ai/chat`

Header:

```text
Content-Type: application/json
X-CSRF-Token: <token dari window.AI_CSRF_TOKEN>
```

Body:

```json
{
  "query": "Berapa status pesanan saya?",
  "history": [
    { "role": "user", "content": "Halo" },
    { "role": "assistant", "content": "Halo, ada yang bisa dibantu?" }
  ]
}
```

Aturan:

- Wajib login.
- Hanya role `customer` dan `admin`.
- CSRF wajib valid.
- Maksimal query 2.000 karakter.
- Maksimal 20 pesan riwayat.

### `POST /ai/generateDescription`

- Wajib login sebagai `customer`.
- CSRF wajib valid.
- Maksimal `brief_input` 1.000 karakter.

### `POST /ai/technicianHelper`

- Wajib login sebagai `technician` atau `operator`.
- CSRF wajib valid.
- `action`: `checklist` atau `report`.

## 4. Format Token HMAC

Token dikirim lewat:

```text
Authorization: Bearer <token>
X-AI-Token: <token>
```

Format:

```text
base64url(payload).base64url(HMAC-SHA256(base64url(payload), secret))
```

Payload:

```json
{
  "v": 1,
  "iss": "php-web",
  "aud": "node-chatbot",
  "sub": "18",
  "role": "customer",
  "scope": "customer",
  "iat": 1760000000,
  "exp": 1760000090,
  "nonce": "32-128 hex characters"
}
```

Aturan verifikasi:

- HMAC SHA-256, perbandingan timing-safe.
- `v` harus `1`.
- `iss` dan `aud` harus sesuai arah request.
- `sub` harus angka user id positif.
- `role` dan `scope` harus sama dan hanya `customer`/`admin`.
- Token berlaku 30–300 detik.
- Toleransi clock skew 15 detik.
- Nonce hanya boleh dipakai sekali.
- Secret minimal 32 karakter.

Arah token:

| Arah | `iss` | `aud` | Pembuat | Verifikator |
|---|---|---|---|---|
| PHP → Node | `php-web` | `node-chatbot` | PHP | Node |
| Node → PHP | `chatbot-node` | `php-internal` | Node | PHP |

## 5. PHP Internal API (Node only)

Semua endpoint:

- Hanya `GET`.
- Wajib HMAC service token.
- Tidak menerima session browser sebagai autentikasi.
- `Cache-Control: no-store`.
- Read-only.

### `GET /api/internal/ai/health`

Mengembalikan status autentikasi, role, scope, dan waktu server. Tidak
mengembalikan data pribadi.

### `GET /api/internal/ai/context`

Mengembalikan kemampuan role:

```json
{
  "success": true,
  "data": {
    "role": "customer",
    "scope": "customer",
    "read_only": true,
    "can_write": false,
    "permissions": {
      "read_services": true,
      "read_own_orders": true,
      "read_all_orders": false,
      "read_customers": false,
      "read_reports": false,
      "write_any_data": false
    }
  }
}
```

### `GET /api/internal/ai/orders?limit=20&status=completed`

Endpoint referensi untuk menguji isolasi data.

- `customer`: query selalu ditambah `c.user_id = <signed user id>`.
- `admin`: dapat membaca seluruh pesanan.
- Parameter `customer_id`/`user_id` dari request **diabaikan**.
- Hanya mengembalikan `order_code`, `status`, `payment_status`, `created_at`,
  dan `service_title`.

`limit` dibatasi 1–50. `status` harus salah satu status resmi.

## 6. Node.js Internal API

Semua endpoint di bawah `/api/internal` membutuhkan handoff token PHP.

### `GET /api/internal/auth-check`

Mengembalikan identitas dari token PHP: `user_id`, `role`, `scope`, dan
`read_only: true`.

### `GET /api/internal/php-context`

Node membuat service token baru, memanggil PHP `/api/internal/ai/context`, lalu
mengembalikan hasilnya. Ini membuktikan autentikasi dua arah.

## 7. OpenRouter

Modul: `chatbot-node/src/services/openrouter.js`.

Konfigurasi hanya di `chatbot-node/.env`:

```env
OPENROUTER_API_KEY=
OPENROUTER_MODEL=
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
OPENROUTER_TIMEOUT_MS=30000
```

Aturan:

- Key dibaca dari `process.env` melalui `dotenv`.
- Key tidak pernah dikirim ke browser, disimpan di database, atau dicetak ke log.
- Jika key/model kosong, service melempar error konfigurasi dan tidak membuat
  jawaban palsu.
- Error 401/403, 404, 429, 5xx, timeout, dan gangguan jaringan ditangani tanpa
  membocorkan key.
- Pemeriksaan konfigurasi: `node scripts/check-openrouter.js`.
- Tes koneksi nyata: `node scripts/check-openrouter.js --live`.

## 8. Error Umum

| HTTP | Arti |
|---|---|
| `400` | JSON/parameter tidak valid |
| `401` | Belum login, token hilang, token kedaluwarsa, atau replay |
| `403` | Role tidak diizinkan atau CSRF gagal |
| `405` | Method salah (endpoint internal yang tidak memiliki route method tersebut dapat mengembalikan `404`) |
| `422` | Validasi input gagal |
| `503` | Secret/konfigurasi internal belum tersedia |

Pesan error tidak boleh memuat token, secret, hash password, atau detail SQL.

## 9. Batas Tahap

Sudah tersedia pada Tahap 2–3:

- Kontrak API.
- HMAC dua arah.
- Replay protection.
- Verifikasi role customer/admin.
- CSRF untuk endpoint browser.
- Endpoint internal read-only referensi.
- Widget hanya dimuat untuk customer/admin.
- Modul client OpenRouter + validasi konfigurasi + skrip cek koneksi.

Belum tersedia sampai tahap berikutnya:

- Migrasi helper Gemini ke OpenRouter.
- Ringkasan otomatis multi-pertanyaan (contoh: "ringkas semua" tanpa rentang).

## 10. Tahap Intelijensi: Identitas, Retrieval Data, dan Function Calling

### 10.1 Alur identitas pengguna

Identitas **tidak pernah** diambil dari pesan pengguna. Alurnya:

```text
PHP session ($_SESSION['user'])
  -> AiInternalAuthService::issueHandoffToken()  (menyisipkan name/email/username)
  -> token HMAC bertanda tangan (aud = node-chatbot)
  -> Node.js internalAuth memverifikasi signature
  -> req.aiUser { name, email, username, role, userId }
  -> system prompt OpenRouter (blok IDENTITAS)
```

Nilai identitas pada token diverifikasi signature HMAC dan dibersihkan
(hanya karakter printable, maksimal 160 karakter). Verifikasi silang ke
database tersedia di `GET /api/internal/ai/data/identity`
(tool `get_user_identity`).

### 10.2 Endpoint retrieval data baru (GET, HMAC, read-only)

Semua di bawah `/api/internal/ai/data/`. Query string hanya boleh mempersempit,
tidak pernah memperluas scope.

| Endpoint | Role | Tool OpenRouter | Isi |
|---|---|---|---|
| `GET /api/internal/ai/data/identity` | customer, admin | `get_user_identity` | Profil akun yang sedang login |
| `GET /api/internal/ai/data/public-info` | customer, admin | `get_public_info` | Kategori layanan, alur pemesanan & pembayaran |
| `GET /api/internal/ai/data/services` | customer, admin | `get_service_list`, `get_service_prices` | Katalog layanan + harga (`view`, `keyword`, `category`) |
| `GET /api/internal/ai/data/my-orders` | customer, admin | `get_my_orders` | Pesanan milik customer yang login |
| `GET /api/internal/ai/data/order-detail` | customer, admin | `get_order_details` | Detail pesanan (`code`); customer dibatasi pesanannya sendiri |
| `GET /api/internal/ai/data/admin/totals` | admin | `get_totals` | Agregat utama website |
| `GET /api/internal/ai/data/admin/customers` | admin | `get_customer_list` | Daftar customer + jumlah pesanan |
| `GET /api/internal/ai/data/admin/orders` | admin | `get_orders_by_period` | Pesanan per periode (`period`, `status`, `limit`) |
| `GET /api/internal/ai/data/admin/orders-summary` | admin | `get_orders_summary` | Agregasi (`group_by`) |
| `GET /api/internal/ai/data/admin/payments` | admin | `get_payment_summary` | Pendapatan & status pembayaran |
| `GET /api/internal/ai/data/admin/technicians` | admin | `get_technician_list` | Teknisi & beban kerja |
| `GET /api/internal/ai/data/admin/popular-services` | admin | `get_popular_services` | Layanan terpopuler |
| `GET /api/internal/ai/data/admin/period-comparison` | admin | `get_period_comparison` | Bulan ini vs bulan lalu |
| `GET /api/internal/ai/data/admin/schema` | admin | `get_database_schema` | Struktur live dari `information_schema` |
| `GET /api/internal/ai/data/admin/activity` | admin | `get_activity_report` | Laporan aktivitas (`days`) |

Role `customer` yang memanggil endpoint `/admin/*` selalu mendapat `403`
(verifikasi di backend, bukan hanya di system prompt).

### 10.3 Function calling

- Node.js mengirim `tools` + `tool_choice: 'auto'` ke OpenRouter.
- Maksimal 4 putaran tool call per percakapan.
- Hasil tool dikembalikan sebagai pesan `role: 'tool'` beserta `tool_call_id`.
- Jika tool gagal, error dikembalikan ke model sebagai `{ success:false, error }`
  sehingga model bisa jujur menginformasikan pengguna.
- Tidak ada tool tulis. Admin tetap read-only; permintaan mengubah data
  ditolak dengan sopan.
