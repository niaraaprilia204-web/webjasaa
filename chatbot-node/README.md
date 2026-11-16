# TKJ Service Hub — Node.js AI Bridge

Folder ini adalah backend Node.js terpisah untuk chatbot AI TKJ Service Hub.
Fondasi autentikasi HMAC sudah tersedia dan modul OpenRouter sudah disiapkan.
Pemanggilan OpenRouter belum dihubungkan ke endpoint browser `/ai/chat` agar
fitur website lama tidak berubah sampai tahap migrasi berikutnya.

## Persyaratan

- Node.js 20 atau lebih baru (di mesin ini: Node.js 24).
- PHP + MySQL aplikasi utama sudah berjalan.
- Secret HMAC yang sama dengan `AI_INTERNAL_SECRET` di `.env` PHP.
- API key OpenRouter dan model OpenRouter pilihan Anda.

## Cara menjalankan

1. Salin `.env.example` menjadi `.env`.

```powershell
cd C:\laragon\www\webjasa\chatbot-node
Copy-Item .env.example .env
```

2. Isi `AI_INTERNAL_SECRET` dengan nilai yang **sama** seperti di
   `C:\laragon\www\webjasa\.env`. Nilai minimal 32 karakter.
   Lalu isi `OPENROUTER_API_KEY` dan `OPENROUTER_MODEL` dengan milik Anda.

3. Install dependency.

```powershell
npm install
```

4. Jalankan server.

```powershell
npm start
```

Server hanya mendengarkan di `127.0.0.1:3001`. Jangan mengubah host menjadi
`0.0.0.0` kecuali Anda benar-benar paham risikonya.

5. Uji dari terminal lain.

```powershell
node scripts/check-php.js customer 18
node scripts/check-php.js admin 15
node scripts/check-openrouter.js
```

Tes koneksi OpenRouter nyata (mengirim satu permintaan kecil):

```powershell
node scripts/check-openrouter.js --live
```

## Endpoint

- `GET /api/internal/auth-check` — memverifikasi handoff token dari PHP.
- `GET /api/internal/php-context` — memanggil PHP internal API dengan service token.

Semua endpoint internal membutuhkan token HMAC. Tidak ada endpoint tulis.

## OpenRouter

Modul OpenRouter ada di `src/services/openrouter.js`.

Lokasi konfigurasi:

```text
C:\laragon\www\webjasa\chatbot-node\.env
```

Isi dua variabel wajib berikut:

```env
OPENROUTER_API_KEY=<API key OpenRouter Anda>
OPENROUTER_MODEL=<model OpenRouter pilihan Anda>
```

Contoh format model (bukan API key):

```text
openai/gpt-4o-mini
google/gemini-2.0-flash-001
```

Aturan:

- API key hanya dibaca dari `process.env` melalui `dotenv`.
- API key tidak pernah dikirim ke browser, ditulis ke database, atau dicetak ke log.
- Jika key/model kosong, service akan gagal dengan pesan konfigurasi dan **tidak**
  membuat jawaban palsu.
- Permintaan OpenRouter memiliki timeout dan penanganan error 401/403/404/429/5xx.
- Saat ini service OpenRouter sudah siap dipakai dari kode Node, tetapi belum
  dihubungkan ke endpoint browser `/ai/chat`. Migrasi chatbot ke OpenRouter
  dilakukan pada tahap berikutnya agar fitur lama tetap berjalan.

## Variabel `.env`

```env
OPENROUTER_API_KEY=
OPENROUTER_MODEL=
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
OPENROUTER_TIMEOUT_MS=30000
PORT=3001
PHP_INTERNAL_URL=http://localhost/webjasa
APP_URL=http://localhost/webjasa
AI_INTERNAL_SECRET=
```

- `OPENROUTER_API_KEY` dan `OPENROUTER_MODEL` wajib diisi untuk memakai OpenRouter.
- `OPENROUTER_BASE_URL` dan `OPENROUTER_TIMEOUT_MS` opsional; nilai default sudah aman.
- Jangan pernah mengirim API key atau `AI_INTERNAL_SECRET` ke browser.
- File `.env` sudah masuk `.gitignore`.

## Keamanan

- Token memakai HMAC-SHA256, audience-bound, dan berumur pendek.
- Nonce dicek agar token tidak bisa dipakai ulang.
- Role hanya berasal dari token yang ditandatangani PHP.
- Node tidak memiliki kredensial database.
- Server hanya bind ke loopback.
- Body request dibatasi 16 KB.

Kontrak lengkap: `../docs/AI_CHATBOT_API_CONTRACT.md`.
