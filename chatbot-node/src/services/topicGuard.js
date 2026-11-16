/**
 * Defense-in-depth relevance guard for the TKJ Service Hub chatbot (Node side).
 *
 * PHP already rejects off-topic questions in AiTopicGuard BEFORE forwarding the
 * request to Node. This module mirrors that check so a request that reaches the
 * Node server through any other path can never combine an off-topic question
 * with database tools.
 */

const STRONG_KEYWORDS = [
  'tkj', 'service hub', 'servis', 'service', 'layanan', 'jasa',
  'pesanan', 'order', 'pesan', 'memesan', 'booking',
  'laporan', 'report', 'komplain', 'keluhan', 'pengaduan',
  'customer', 'admin', 'operator', 'teknisi', 'pengguna',
  'akun', 'profil', 'identitas', 'login', 'registrasi', 'register',
  'database', 'data', 'tabel', 'statistik',
  'harga', 'biaya', 'tarif', 'pembayaran', 'payment', 'bayar', 'lunas',
  'cctv', 'wifi', 'wi-fi', 'wireless', 'jaringan', 'lan', 'mikrotik',
  'router', 'internet', 'koneksi', 'printer', 'komputer', 'laptop',
  'notebook', 'hardware', 'software', 'instalasi', 'pemasangan',
  'maintenance', 'perawatan', 'perbaikan', 'repair', 'troubleshooting',
  'diagnosa', 'kendala', 'kerusakan', 'rusak', 'garansi',
  'notifikasi', 'whatsapp', 'fonnte', 'otp',
  'alur', 'proses', 'panduan', 'tutorial', 'fitur', 'sistem',
  'website', 'web', 'aplikasi', 'platform', 'unit bisnis', 'sekolah',
  'siswa', 'teknik komputer', 'status', 'jadwal', 'kunjungan',
  'selesai', 'dibatalkan', 'diproses',
];

const OFFTOPIC_PATTERNS = [
  'python', 'coding', 'kode program', 'tulis kode', 'buatkan kode', 'buat kode',
  'program python', 'javascript', 'java script', 'sql query',
  'algoritma',
  'masakan', 'resep', 'memasak', 'menu makanan', 'masak ', 'bahan makanan',
  'film', 'movie', 'cerita', 'dongeng', 'novel', 'lagu', 'musik', 'penyanyi',
  'presiden', 'negara lain', 'politik', 'pemilu', 'gubernur',
  'cuaca', 'olahraga', 'sepak bola', 'pertandingan',
  'agama', 'doa', 'sholat',
  'matematika', 'pekerjaan rumah', 'fisika', 'kimia', 'biologi',
  'penyakit', 'obat', 'dokter', 'kesehatan',
  'saham', 'investasi', 'crypto', 'bitcoin',
  'pacar', 'jodoh', 'horoskop', 'zodiak',
  'game', 'bermain',
  'baju', 'fashion', 'jam tangan', 'sepatu',
];

const GREETING_PATTERN = /^(halo|hai|hi|hello|hei|selamat\s+(pagi|siang|sore|malam|datang)|terima\s+kasih|makasih|thanks|thank\s+you|apa\s+kabar|assalamualaikum|sampai\s+jumpa|oke|ok|siap|ya)\W*$/i;

function normalize(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/[^\p{L}\p{N}\s-]/gu, ' ')
    .replace(/\s+/gu, ' ')
    .trim();
}

function containsAny(haystack, needles) {
  return needles.some((needle) => haystack.includes(needle));
}

/**
 * Mirror of PHP AiTopicGuard::isRelevant().
 */
function isRelevant(query) {
  const normalized = normalize(query);
  if (normalized === '') {
    return false;
  }
  if (GREETING_PATTERN.test(normalized)) {
    return true;
  }
  const hasOfftopic = containsAny(normalized, OFFTOPIC_PATTERNS);
  const hasStrong = containsAny(normalized, STRONG_KEYWORDS);
  if (hasOfftopic) {
    return hasStrong;
  }
  return hasStrong;
}

/**
 * Standard friendly rejection message (must match PHP AiTopicGuard).
 */
function rejectionMessage() {
  return 'Maaf, saya adalah TKJ Service Hub AI Assistant. '
    + 'Saya hanya dapat membantu pertanyaan seputar website TKJ Service Hub, data, layanan, '
    + 'pesanan, laporan, dan informasi sistem kami. Ada yang ingin Anda tanyakan tentang '
    + 'layanan TKJ Service Hub?';
}

/**
 * The last real user message of the conversation (skips tool/assistant turns).
 */
function lastUserQuery(messages = []) {
  for (let i = messages.length - 1; i >= 0; i -= 1) {
    const message = messages[i];
    if (message && message.role === 'user' && typeof message.content === 'string') {
      return message.content;
    }
  }
  return '';
}

module.exports = {
  isRelevant,
  rejectionMessage,
  lastUserQuery,
};
