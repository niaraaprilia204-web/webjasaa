<?php

/**
 * Backend relevance guard for the TKJ Service Hub AI Assistant.
 *
 * The assistant is intentionally scoped to TKJ Service Hub topics only. This
 * guard runs on the server BEFORE the chat request is forwarded to Node.js /
 * OpenRouter and before any database tool can be executed, so an off-topic
 * question can never trigger a database read.
 *
 * The check is deliberately keyword based (no AI call) so it is fast, free,
 * deterministic, and easy to audit.
 */
class AiTopicGuard
{
    /**
     * Standard, friendly rejection message (Bahasa Indonesia).
     */
    public const REJECTION_MESSAGE = 'Maaf, saya adalah TKJ Service Hub AI Assistant. '
        . 'Saya hanya dapat membantu pertanyaan seputar website TKJ Service Hub, data, layanan, '
        . 'pesanan, laporan, dan informasi sistem kami. Ada yang ingin Anda tanyakan tentang '
        . 'layanan TKJ Service Hub?';

    /**
     * Strong TKJ Service Hub signals (domain nouns). If any of these appear,
     * the question is treated as on-topic even when a weak off-topic word
     * also appears. Matched with word boundaries.
     */
    private const STRONG_KEYWORDS = [
        'tkj', 'service hub', 'servis', 'service', 'layanan', 'jasa',
        'pesanan', 'order', 'memesan', 'booking',
        'laporan', 'report', 'komplain', 'keluhan', 'pengaduan',
        'customer', 'admin', 'operator', 'teknisi', 'pengguna',
        'akun', 'profil', 'identitas', 'login', 'registrasi', 'register',
        'database', 'tabel', 'statistik',
        'harga', 'biaya', 'tarif', 'pembayaran', 'payment', 'bayar', 'lunas',
        'cctv', 'wifi', 'wi fi', 'wireless', 'jaringan', 'mikrotik',
        'router', 'internet', 'koneksi', 'printer', 'komputer', 'laptop',
        'notebook', 'hardware', 'software', 'instalasi', 'pemasangan',
        'maintenance', 'perawatan', 'perbaikan', 'repair', 'troubleshooting',
        'diagnosa', 'kendala', 'kerusakan', 'rusak', 'garansi', 'jaminan',
        'notifikasi', 'whatsapp', 'fonnte', 'otp',
        'alur', 'panduan', 'tutorial', 'fitur', 'sistem', 'website',
        'web', 'aplikasi', 'platform', 'unit bisnis', 'sekolah',
        'siswa', 'teknik komputer', 'jadwal', 'kunjungan',
    ];

    /**
     * Generic question words. Alone they are not enough for a topic match,
     * but they pass when no off-topic pattern is present.
     */
    private const WEAK_KEYWORDS = [
        'siapa', 'berapa', 'data', 'jumlah', 'total', 'daftar', 'tampilkan',
        'tolong', 'bantu', 'bantuan', 'bagaimana', 'apa', 'kapan',
        'proses', 'status', 'selesai', 'dibatalkan', 'diproses', 'wa',
    ];

    /**
     * Words that strongly indicate a request outside the TKJ scope.
     */
    private const OFFTOPIC_PATTERNS = [
        'python', 'coding', 'kode program', 'tulis kode', 'buatkan kode', 'buat kode',
        'program python', 'javascript', 'java script', 'sql query',
        'algoritma',
        'masakan', 'resep', 'memasak', 'menu makanan', 'masak', 'bahan makanan',
        'film', 'movie', 'dongeng', 'novel', 'lagu', 'musik', 'penyanyi',
        'presiden', 'negara lain', 'politik', 'pemilu', 'gubernur',
        'cuaca', 'olahraga', 'sepak bola', 'pertandingan',
        'sholat', 'ibadah',
        'pekerjaan rumah', 'fisika', 'kimia', 'biologi',
        'penyakit', 'obat', 'dokter',
        'saham', 'investasi', 'crypto', 'bitcoin',
        'pacar', 'jodoh', 'horoskop', 'zodiak',
        'game', 'bermain',
        'fashion', 'jam tangan', 'sepatu',
    ];

    /**
     * Greetings / small talk are always allowed (no database access needed).
     * Matched only against the WHOLE short message so a sentence that merely
     * starts with "tolong"/"siapa" is not treated as a greeting.
     */
    private const GREETING_PATTERN = '/^(halo|hai|hi|hello|hei|halo+|selamat\s+(pagi|siang|sore|malam|datang|tinggal)'
        . '|terima\s+kasih|makasih|makasih\s+ya|thanks|thank\s+you|apa\s+kabar|assalamualaikum|'
        . 'sampai\s+jumpa|oke|ok|siap|ya)\W*$/i';

    /**
     * Determine whether a user question is relevant to TKJ Service Hub.
     */
    public static function isRelevant(string $query): bool
    {
        $normalized = self::normalize($query);

        if ($normalized === '') {
            return false;
        }

        if (preg_match(self::GREETING_PATTERN, $normalized) === 1) {
            return true;
        }

        $hasOfftopic = self::containsOfftopic($normalized);
        $hasStrong = self::containsStrongKeyword($normalized);
        $hasWeak = self::containsWeakKeyword($normalized);

        if ($hasOfftopic) {
            // Off-topic intent wins unless there is an explicit TKJ data entity.
            return $hasStrong;
        }

        return $hasStrong || $hasWeak;
    }

    /**
     * Machine-readable reason, useful for logs and tests.
     */
    public static function reason(string $query): string
    {
        $normalized = self::normalize($query);

        if ($normalized === '') {
            return 'empty';
        }
        if (preg_match(self::GREETING_PATTERN, $normalized) === 1) {
            return 'greeting';
        }
        if (self::containsOfftopic($normalized)) {
            return self::containsStrongKeyword($normalized) ? 'relevant_despite_offtopic' : 'offtopic';
        }
        if (self::containsStrongKeyword($normalized) || self::containsWeakKeyword($normalized)) {
            return 'relevant';
        }

        return 'offtopic';
    }

    public static function rejectionMessage(): string
    {
        return self::REJECTION_MESSAGE;
    }

    private static function normalize(string $query): string
    {
        $value = strtolower(trim($query));
        $value = preg_replace('/[^\p{L}\p{N}\s\-]/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', (string) $value);

        return trim((string) $value);
    }

    private static function containsOfftopic(string $normalized): bool
    {
        return self::matchesKeyword($normalized, self::OFFTOPIC_PATTERNS);
    }

    private static function containsStrongKeyword(string $normalized): bool
    {
        return self::matchesKeyword($normalized, self::STRONG_KEYWORDS);
    }

    /**
     * Weaker signals used only when no off-topic pattern is present.
     */
    private static function containsWeakKeyword(string $normalized): bool
    {
        return self::matchesKeyword($normalized, self::WEAK_KEYWORDS);
    }

    /**
     * Whole-word (space delimited) matching, because normalize() already
     * collapsed punctuation to spaces. Prevents substrings such as "wa" in
     * "software" or "siapa" inside "Siapakan" from triggering a match.
     */
    private static function matchesKeyword(string $normalized, array $keywords): bool
    {
        $padded = ' ' . $normalized . ' ';

        foreach ($keywords as $keyword) {
            $keyword = trim((string) $keyword);
            if ($keyword === '') {
                continue;
            }
            if (strpos($padded, ' ' . $keyword . ' ') !== false) {
                return true;
            }
            // Multi-word keywords may also appear at the very end of the query.
            if (str_ends_with($padded, ' ' . $keyword . ' ') === false
                && str_starts_with($keyword, ' ') === false
                && preg_match('/ ' . preg_quote($keyword, '/') . '$/u', $normalized) === 1) {
                return true;
            }
        }

        return false;
    }
}
