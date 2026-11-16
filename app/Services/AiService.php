<?php

require_once __DIR__ . '/BaseService.php';

/**
 * AI Integration & Expert Knowledge Engine Service
 */
class AiService extends BaseService
{
    private $apiKey;
    private $model;
    private $apiEnabled = true;
    private $temperature = 0.4;
    private $extraInstruction = '';

    public function __construct()
    {
        $this->apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
        $this->model = defined('AI_MODEL_NAME') ? AI_MODEL_NAME : 'gemini-2.5-flash';

        // Load non-secret operator settings. If the additive migration has not
        // been run yet, keep using the environment defaults.
        try {
            require_once __DIR__ . '/AiSettingsService.php';
            $settings = (new AiSettingsService())->getSettings();
            $this->apiEnabled = !empty($settings['api_enabled']);
            if (!empty($settings['model'])) {
                $this->model = $settings['model'];
            }
            $this->temperature = (float) ($settings['temperature'] ?? 0.4);
            $this->extraInstruction = trim((string) ($settings['extra_instruction'] ?? ''));
        } catch (\Throwable $e) {
            // Fail open to the existing environment configuration.
        }
    }


    /**
     * Get Real Database Service Catalog Context
     */
    private function getServicesContext()
    {
        try {
            require_once __DIR__ . '/../Models/Service.php';
            $serviceModel = new Service();
            $services = $serviceModel->allWithCategory();
            if (!empty($services)) {
                $lines = [];
                foreach ($services as $s) {
                    $cat = $s['category_name'] ?? 'Layanan IT';
                    $title = $s['title'] ?? '';
                    $desc = $s['description'] ?? '';
                    $dur = $s['estimated_duration'] ?? '-';
                    $price = $s['price_range'] ?? '-';
                    $lines[] = "• **{$title}** (Kategori: {$cat})\n  - Deskripsi: {$desc}\n  - Estimasi Pengerjaan: {$dur}\n  - Rentang Biaya: {$price}";
                }
                return implode("\n", $lines);
            }
        } catch (\Throwable $e) {
            // Log error silently and use fallback catalog
        }

        return "• **Instalasi CCTV** (Kategori: Instalasi & Perangkat) | Estimasi: 1 hari | Biaya: Rp 150.000 - Rp 500.000\n"
             . "• **Instalasi Jaringan LAN** (Kategori: Instalasi & Perangkat) | Estimasi: 3-5 jam | Biaya: Rp 50.000 - Rp 250.000\n"
             . "• **Konfigurasi Wi-Fi** (Kategori: Jaringan & Software) | Estimasi: 2-4 jam | Biaya: Rp 75.000 - Rp 300.000\n"
             . "• **Service Komputer** (Kategori: Perbaikan Hardware & Laptop) | Estimasi: 1-2 hari | Biaya: Rp 50.000 - Rp 200.000\n"
             . "• **Service Laptop** (Kategori: Perbaikan Hardware & Laptop) | Estimasi: 1-3 hari | Biaya: Rp 75.000 - Rp 350.000\n"
             . "• **Instalasi Software** (Kategori: Jaringan & Software) | Estimasi: 1-2 jam | Biaya: Rp 35.000 - Rp 100.000\n"
             . "• **Maintenance Komputer** (Kategori: Maintenance & Konsultasi) | Estimasi: 1 hari | Biaya: Rp 100.000 - Rp 400.000\n"
             . "• **Troubleshooting Jaringan** (Kategori: Jaringan & Software) | Estimasi: 1-3 jam | Biaya: Rp 50.000 - Rp 150.000\n"
             . "• **Service Printer** (Kategori: Perbaikan Hardware & Laptop) | Estimasi: 1 hari | Biaya: Rp 50.000 - Rp 175.000\n"
             . "• **Jasa IT Lainnya** (Kategori: Maintenance & Konsultasi) | Estimasi: Tentatif | Biaya: Rp 50.000 - Rp 300.000";
    }

    /**
     * Main Chat Response Handler (Customer Service & Troubleshooting Assistant)
     */
    public function chatResponse($userQuery, $context = [])
    {
        $queryClean = trim($userQuery);
        if (empty($queryClean)) {
            return [
                'success' => false,
                'message' => 'Pertanyaan tidak boleh kosong.'
            ];
        }

        // Try Live API first if key is present
        if ($this->apiEnabled && !empty($this->apiKey)) {
            $apiResult = $this->callGeminiApi($queryClean, $context);
            if ($apiResult['success']) {
                return $apiResult;
            }
        }

        // Fallback to Intelligent Local TKJ Knowledge Base Engine
        $localResult = $this->localKnowledgeEngine($queryClean, $context);
        return [
            'success' => true,
            'source'  => 'local_engine',
            'data'    => [
                'reply'          => $localResult['reply'],
                'category'       => $localResult['category'],
                'suggestedAction'=> $localResult['suggestedAction'],
                'relatedService' => $localResult['relatedService']
            ]
        ];
    }

    /**
     * Problem Description Generator Helper for Customer Order Form
     */
    public function generateProblemDescription($briefInput, $serviceType = '')
    {
        $briefClean = trim($briefInput);
        if (empty($briefClean)) {
            return [
                'success' => false,
                'message' => 'Masukkan catatan singkat mengenai masalah perangkat Anda.'
            ];
        }

        if ($this->apiEnabled && !empty($this->apiKey)) {
            $prompt = "Sebagai asisten teknis TKJ Service Hub, ubah catatan singkat pelanggan berikut menjadi deskripsi masalah teknis yang terstruktur, rapi, dan rinci untuk teknisi.\n"
                    . "Kategori Layanan: {$serviceType}\n"
                    . "Catatan Pelanggan: \"{$briefClean}\"\n\n"
                    . "Format output:\n"
                    . "- Gejala Utama: ...\n"
                    . "- Kondisi Perangkat: ...\n"
                    . "- Kebutuhan / Harapan Perbaikan: ...";

            $apiResult = $this->callGeminiApi($prompt);
            if ($apiResult['success']) {
                return [
                    'success' => true,
                    'description' => $apiResult['data']['reply']
                ];
            }
        }

        // Rule-based description formatter
        $formatted = "📌 GEJALA UTAMA:\n"
                   . "- " . ucfirst($briefClean) . "\n\n"
                   . "⚙️ KONDISI PERANGKAT:\n"
                   . "- Perangkat mengalami kendala operasional pada unit " . ($serviceType ?: 'teknologi/elektronik') . ".\n\n"
                   . "🛠️ PERMINTAAN PERBAIKAN:\n"
                   . "- Membutuhkan pemeriksaan fisik, diagnosa hardware/software, dan perbaikan menyeluruh oleh teknisi TKJ.";

        return [
            'success' => true,
            'source'  => 'local_engine',
            'description' => $formatted
        ];
    }

    /**
     * Technician Troubleshooting Checklist Generator
     */
    public function generateTechnicianChecklist($serviceType, $problemDescription)
    {
        if ($this->apiEnabled && !empty($this->apiKey)) {
            $prompt = "Sebagai Instruktur Senior TKJ, buatkan 5 langkah Checklist Troubleshooting Diagnosa Fisik untuk teknisi siswa TKJ.\n"
                    . "Layanan: {$serviceType}\n"
                    . "Deskripsi Masalah: \"{$problemDescription}\"\n\n"
                    . "Berikan checklist berbentuk poin-poin yang tepat, sistematis, dan aman bagi keselamatan kerja (K3).";

            $apiResult = $this->callGeminiApi($prompt);
            if ($apiResult['success']) {
                return [
                    'success' => true,
                    'checklist' => $apiResult['data']['reply']
                ];
            }
        }

        // Local checklist generator based on service category
        $checklist = $this->getLocalTechnicianChecklist($serviceType, $problemDescription);
        return [
            'success' => true,
            'source'  => 'local_engine',
            'checklist' => $checklist
        ];
    }

    /**
     * Technician Work Report Summary Generator
     */
    public function generateWorkReportSummary($orderCode, $serviceType, $findings, $actions)
    {
        if ($this->apiEnabled && !empty($this->apiKey)) {
            $prompt = "Buatkan Ringkasan Laporan Pengerjaan Teknisi (Work Completion Report) yang profesional dan mudah dipahami pelanggan.\n"
                    . "Kode Order: {$orderCode}\n"
                    . "Jenis Layanan: {$serviceType}\n"
                    . "Hasil Diagnosa/Temuan: {$findings}\n"
                    . "Tindakan Perbaikan: {$actions}\n";

            $apiResult = $this->callGeminiApi($prompt);
            if ($apiResult['success']) {
                return [
                    'success' => true,
                    'report' => $apiResult['data']['reply']
                ];
            }
        }

        $report = "📋 **LAPORAN PENGERJAAN TEKNISI (SPK #{$orderCode})**\n\n"
                . "🔹 **Jenis Layanan**: " . htmlspecialchars($serviceType) . "\n"
                . "🔍 **Hasil Diagnosa & Temuan**: " . htmlspecialchars($findings) . "\n"
                . "🛠️ **Tindakan Perbaikan**: " . htmlspecialchars($actions) . "\n"
                . "✅ **Status Akhir**: Pengerjaan selesai & perangkat telah diuji fungsi (QC Passed oleh Instruktur TKJ).";

        return [
            'success' => true,
            'source'  => 'local_engine',
            'report'  => $report
        ];
    }

    /**
     * Call Google Gemini Generative AI REST API
     */
    private function callGeminiApi($userPrompt, $context = [])
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key=" . $this->apiKey;

        $dbServices = $this->getServicesContext();

        $systemInstructionText = "Anda adalah 'TKJ AI Assistant', asisten virtual resmi untuk 'TKJ Service Hub', platform unit bisnis siswa Teknik Komputer & Jaringan (TKJ).\n\n"
            . "IDENTITAS & ATURAN BEHAVIOR:\n"
            . "- Nama: TKJ AI Assistant.\n"
            . "- Peran: Asisten Virtual TKJ Service Hub.\n"
            . "- Bahasa: Gunakan Bahasa Indonesia yang natural, ramah, sopan, dan mudah dipahami.\n"
            . "- Identitas: Jika ditanya 'Siapa Anda?', 'Kamu siapa?', 'Siapa kamu?', atau 'Siapa nama kamu?', perkenalkan diri secara singkat: 'Halo! Saya TKJ AI Assistant, asisten virtual TKJ Service Hub. Saya siap membantu Anda mendapatkan informasi layanan dan bantuan teknis.' Jangan menambahkan daftar diagnosis jika tidak diminta.\n"
            . "- Kejujuran AI: Jika ditanya apakah Anda manusia atau AI, jawab dengan jujur bahwa Anda adalah AI (Asisten Virtual), namun seluruh pengerjaan teknis di lapangan dikerjakan langsung oleh siswa-siswi TKJ berpengalaman dengan pengawasan instruktur.\n"
            . "- Sapaan & Ucapan Terima Kasih: Balas sapaan (halo, selamat pagi, dll) dan ucapan terima kasih secara hangat dan sopan.\n"
            . "- BATASAN TUGAS (PENTING): Jika pengguna meminta permintaan pemrograman/coding umum di luar layanan TKJ Service Hub (misalnya 'Buatkan codingan Python untuk menuliskan nama'), gunakan jawaban berikut secara konsisten:\n"
            . "  'Maaf, karena saya adalah TKJ AI Assistant yang dirancang khusus untuk membantu informasi layanan dan permasalahan teknis TKJ Service Hub, saya belum dapat membantu permintaan pemrograman umum yang berada di luar tugas utama saya. Saya dapat membantu Anda terkait CCTV, jaringan komputer, perbaikan laptop dan komputer, printer, serta bantuan teknis lainnya. Apakah ada kendala teknis yang ingin Anda tanyakan?'\n\n"
            . "DATA RESMI JASA & HARGA TKJ SERVICE HUB (DARI DATABASE):\n"
            . $dbServices . "\n\n"
            . "PETUNJUK RESPONS:\n"
            . "1. Gunakan data layanan dan harga resmi di atas untuk menjawab pertanyaan tentang estimasi biaya, durasi, dan jenis perbaikan. Jangan mengarang harga atau garansi di luar data.\n"
            . "2. Berikan diagnosa teknis awal dan langkah troubleshooting mandiri yang praktis untuk masalah CCTV, Wi-Fi, jaringan, laptop, komputer, printer, dll. Jawab langsung topik yang ditanyakan tanpa menambahkan daftar diagnosa berulang di luar topik.\n"
            . "3. Pertahankan konteks dari riwayat percakapan sebelumnya.\n"
            . "4. Jika informasi tidak tersedia (seperti status pesanan tanpa kode valid), sampaikan dengan jujur bahwa Anda belum memiliki datanya.";

        if ($this->extraInstruction !== '') {
            $systemInstructionText .= "\n\nINSTRUKSI TAMBAHAN DARI OPERATOR (tetap patuhi aturan keamanan dan batasan tugas di atas):\n"
                . $this->extraInstruction;
        }

        $formattedContents = [];
        $history = $context['history'] ?? [];

        if (!empty($history) && is_array($history)) {
            foreach ($history as $msg) {
                $role = (isset($msg['role']) && ($msg['role'] === 'user' || $msg['role'] === 'human')) ? 'user' : 'model';
                $text = '';
                if (isset($msg['content'])) {
                    $text = $msg['content'];
                } elseif (isset($msg['parts'][0]['text'])) {
                    $text = $msg['parts'][0]['text'];
                }
                if (!empty($text)) {
                    $formattedContents[] = [
                        'role' => $role,
                        'parts' => [['text' => $text]]
                    ];
                }
            }
        }

        $formattedContents[] = [
            'role' => 'user',
            'parts' => [['text' => $userPrompt]]
        ];

        $payload = [
            'system_instruction' => [
                'parts' => [
                    ['text' => $systemInstructionText]
                ]
            ],
            'contents' => $formattedContents,
            'generationConfig' => [
                'temperature' => $this->temperature,
                'maxOutputTokens' => 1000,
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200 || empty($response)) {
            return ['success' => false, 'error' => 'API Error or Timeout (HTTP ' . $httpCode . ')'];
        }

        $json = json_decode($response, true);
        if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
            return [
                'success' => true,
                'source'  => 'gemini_api',
                'data'    => [
                    'reply'          => trim($json['candidates'][0]['content']['parts'][0]['text']),
                    'category'       => 'ai_generated',
                    'suggestedAction'=> 'Pesan Layanan Sekarang',
                    'relatedService' => 'TKJ Service Hub'
                ]
            ];
        }

        return ['success' => false, 'error' => 'Invalid API Response Format'];
    }

    /**
     * Local TKJ Knowledge Base Engine (Rich Rule-Based AI Engine)
     */
    private function localKnowledgeEngine($query, $context = [])
    {
        $q = mb_strtolower(trim($query));
        $history = $context['history'] ?? [];

        // Determine last topic from conversation history for follow-up questions
        $lastTopic = '';
        if (!empty($history) && is_array($history)) {
            $reversed = array_reverse($history);
            foreach ($reversed as $hMsg) {
                $hText = mb_strtolower(is_string($hMsg) ? $hMsg : ($hMsg['content'] ?? ''));
                if (str_contains($hText, 'laptop') || str_contains($hText, 'komputer') || str_contains($hText, 'pc')) {
                    $lastTopic = 'laptop';
                    break;
                }
                if (str_contains($hText, 'cctv') || str_contains($hText, 'dvr')) {
                    $lastTopic = 'cctv';
                    break;
                }
                if (str_contains($hText, 'wifi') || str_contains($hText, 'wi-fi') || str_contains($hText, 'jaringan')) {
                    $lastTopic = 'wifi';
                    break;
                }
                if (str_contains($hText, 'printer')) {
                    $lastTopic = 'printer';
                    break;
                }
            }
        }

        // 0. Out-of-Scope General Coding / Programming Queries (e.g. "Buatkan codingan Python untuk menuliskan nama")
        if (
            str_contains($q, 'python') || 
            str_contains($q, 'codingan') || 
            str_contains($q, 'kodingan') || 
            str_contains($q, 'script') || 
            str_contains($q, 'coding') || 
            str_contains($q, 'pemrograman') ||
            (str_contains($q, 'buatkan') && (str_contains($q, 'kode') || str_contains($q, 'program')))
        ) {
            return [
                'reply' => "Maaf, karena saya adalah TKJ AI Assistant yang dirancang khusus untuk membantu informasi layanan dan permasalahan teknis TKJ Service Hub, saya belum dapat membantu permintaan pemrograman umum yang berada di luar tugas utama saya. Saya dapat membantu Anda terkait CCTV, jaringan komputer, perbaikan laptop dan komputer, printer, serta bantuan teknis lainnya. Apakah ada kendala teknis yang ingin Anda tanyakan?",
                'category' => 'OutOfScope',
                'suggestedAction' => 'Tanyakan Layanan TKJ',
                'relatedService'  => 'Jasa IT & Konsultasi Teknis'
            ];
        }

        // 1. Identity Queries ("Siapa Anda?", "Kamu siapa?", "Siapa nama kamu?", "Anda siapa?", "Siapa kamu?", "Nama kamu siapa?")
        if (
            str_contains($q, 'siapa anda') ||
            str_contains($q, 'anda siapa') ||
            str_contains($q, 'kamu siapa') || 
            str_contains($q, 'siapa kamu') || 
            str_contains($q, 'siapa nama anda') ||
            str_contains($q, 'siapa nama kamu') ||
            str_contains($q, 'nama kamu siapa') ||
            str_contains($q, 'nama anda siapa') ||
            str_contains($q, 'siapa nama') || 
            str_contains($q, 'nama kamu') ||
            str_contains($q, 'nama anda') ||
            str_contains($q, 'tugas kamu') ||
            str_contains($q, 'tugas anda') ||
            str_contains($q, 'bisa kamu bantu') ||
            str_contains($q, 'bisa dibantu') ||
            str_contains($q, 'kemampuan') ||
            $q === 'siapa'
        ) {
            return [
                'reply' => "Halo! Saya **TKJ AI Assistant**, asisten virtual TKJ Service Hub. Saya siap membantu Anda mendapatkan informasi layanan dan bantuan teknis.",
                'category' => 'Identity',
                'suggestedAction' => 'Lihat Katalog Layanan',
                'relatedService'  => 'Jasa IT & Konsultasi Teknis'
            ];
        }

        // 2. Human vs AI Queries ("Kamu manusia atau AI?")
        if (
            str_contains($q, 'manusia atau ai') || 
            str_contains($q, 'apakah kamu manusia') || 
            str_contains($q, 'apakah anda manusia') || 
            str_contains($q, 'kamu ai') || 
            str_contains($q, 'kamu bot') ||
            str_contains($q, 'robot atau manusia') ||
            str_contains($q, 'apakah kamu ai')
        ) {
            return [
                'reply' => "Saya adalah **AI (Artificial Intelligence / Asisten Virtual)** yang diprogram khusus untuk membantu layanan di TKJ Service Hub.\n\nMeski saya sebuah sistem AI kecerdasan buatan, seluruh pekerjaan teknis fisik di lapangan (seperti perbaikan laptop, instalasi CCTV, & setting jaringan) dikerjakan langsung secara profesional oleh **siswa-siswi pilihan jurusan TKJ** dengan pengawasan langsung dari **Instruktur/Guru Teknis**.",
                'category' => 'Identity',
                'suggestedAction' => 'Pesan Layanan Teknis',
                'relatedService'  => 'Jasa IT & Konsultasi Teknis'
            ];
        }

        // 3. Greetings ("Halo, selamat pagi", "Hai", etc.)
        if (
            str_contains($q, 'halo') || 
            str_contains($q, 'hai') || 
            str_contains($q, 'helo') || 
            str_contains($q, 'selamat pagi') || 
            str_contains($q, 'selamat siang') || 
            str_contains($q, 'selamat sore') || 
            str_contains($q, 'selamat malam') ||
            $q === 'pagi' || $q === 'siang' || $q === 'sore' || $q === 'malam'
        ) {
            return [
                'reply' => "Halo, selamat datang! 👋 Ada yang bisa **TKJ AI Assistant** bantu terkait kendala perangkat komputer, jaringan Wi-Fi, CCTV, atau printer Anda hari ini?",
                'category' => 'Greeting',
                'suggestedAction' => 'Tanyakan Kendala',
                'relatedService'  => 'Jasa IT & Konsultasi Teknis'
            ];
        }

        // 4. Thank You Greetings ("Terima kasih")
        if (
            str_contains($q, 'terima kasih') || 
            str_contains($q, 'terimakasih') || 
            str_contains($q, 'makasih') || 
            str_contains($q, 'maturnuwun') || 
            str_contains($q, 'thanks') || 
            str_contains($q, 'thx')
        ) {
            return [
                'reply' => "Sama-sama! 😊 Senang sekali bisa membantu Anda. Jika Anda memerlukan bantuan atau diagnosa teknis lainnya di kemudian hari, jangan ragu untuk menghubungi **TKJ AI Assistant** kembali. Semoga harimu menyenangkan!",
                'category' => 'Greeting',
                'suggestedAction' => 'Pesan Layanan Sekarang',
                'relatedService'  => 'Jasa IT & Konsultasi Teknis'
            ];
        }

        // 5. Available Services Catalog Query ("Apa saja layanan yang tersedia?")
        if (
            str_contains($q, 'apa saja layanan') || 
            str_contains($q, 'daftar layanan') || 
            str_contains($q, 'layanan yang tersedia') || 
            str_contains($q, 'katalog') || 
            str_contains($q, 'service apa saja') || 
            str_contains($q, 'pilihan layanan') ||
            $q === 'layanan'
        ) {
            $dbServicesStr = $this->getServicesContext();
            return [
                'reply' => "🛠️ **Katalog Layanan Resmi TKJ Service Hub:**\n\n" . $dbServicesStr . "\n\nSilakan sebutkan kendala perangkat Anda atau pilih layanan yang ingin Anda pesan!",
                'category' => 'Catalog',
                'suggestedAction' => 'Pesan Layanan',
                'relatedService'  => 'Katalog Layanan'
            ];
        }

        // 5b. Order Procedure Queries ("Bagaimana cara memesan layanan?")
        if (
            str_contains($q, 'cara memesan') || 
            str_contains($q, 'cara pesan') || 
            str_contains($q, 'bagaimana pesan') || 
            str_contains($q, 'prosedur pesan') || 
            str_contains($q, 'cara pemesanan')
        ) {
            return [
                'reply' => "📝 **Langkah Mudah Memesan Layanan TKJ Service Hub:**\n\n"
                         . "1. **Pilih Layanan**: Buka menu **Pesan Jasa** atau klik tombol pemesanan di widget ini.\n"
                         . "2. **Isi Deskripsi Kendala**: Tuliskan catatan singkat kendala perangkat Anda (bisa gunakan tombol *AI Merumuskan* pada form order).\n"
                         . "3. **Tentukan Jadwal & Alamat**: Pilih tanggal dan jam kunjungan teknisi siswa yang Anda kehendaki.\n"
                         . "4. **Konfirmasi & Penugasan**: Operator kami akan memverifikasi order dan menugaskan tim siswa TKJ beserta instruktur pendamping.\n\n"
                         . "Ingin langsung melakukan pemesanan sekarang?",
                'category' => 'Ordering',
                'suggestedAction' => 'Pesan Jasa Sekarang',
                'relatedService'  => 'Pemesanan Layanan'
            ];
        }

        // 6. Pricing & Order Queries (including follow-ups)
        if (str_contains($q, 'harga') || str_contains($q, 'biaya') || str_contains($q, 'tarif') || str_contains($q, 'berapa')) {
            if (str_contains($q, 'laptop') || $lastTopic === 'laptop') {
                return [
                    'reply' => "💻 **Informasi Biaya Service Laptop:**\n\n"
                             . "• **Estimasi Biaya**: Rp 75.000 - Rp 350.000 (tergantung jenis perbaikan hardware/software).\n"
                             . "• **Estimasi Durasi**: 1-3 hari kerja.\n"
                             . "• **Cakupan Perbaikan**: Cleaning kipas & thermal paste, perbaikan keyboard/engsel, ganti LCD, hingga upgrade SSD/RAM.\n"
                             . "• **Garansi**: Garansi pengerjaan gratis 14-30 hari.\n\n"
                             . "Apakah Anda ingin memesan jadwal pemeriksaan laptop sekarang?",
                    'category' => 'PC/Laptop',
                    'suggestedAction' => 'Pesan Service Laptop',
                    'relatedService'  => 'Service Laptop'
                ];
            }
            if (str_contains($q, 'cctv') || $lastTopic === 'cctv') {
                return [
                    'reply' => "📹 **Informasi Biaya Instalasi & Service CCTV:**\n\n"
                             . "• **Estimasi Biaya**: Rp 150.000 - Rp 500.000 (tergantung jumlah kamera & tingkat kesulitan jalur kabel).\n"
                             . "• **Estimasi Durasi**: 1 hari kerja.\n"
                             . "• **Fasilitas**: Pemasangan IP Cam/DVR, penataan kabel rapi, & konfigurasinya ke smartphone.\n\n"
                             . "Silakan klik tombol di bawah untuk menjadwalkan kedatangan teknisi CCTV!",
                    'category' => 'CCTV',
                    'suggestedAction' => 'Pesan Service CCTV',
                    'relatedService'  => 'Instalasi CCTV'
                ];
            }
            if (str_contains($q, 'wifi') || str_contains($q, 'wi-fi') || str_contains($q, 'jaringan') || $lastTopic === 'wifi') {
                return [
                    'reply' => "🌐 **Informasi Biaya Jaringan & Wi-Fi:**\n\n"
                             . "• **Troubleshooting Jaringan**: Rp 50.000 - Rp 150.000 (Estimasi 1-3 jam)\n"
                             . "• **Konfigurasi Wi-Fi & Mikrotik**: Rp 75.000 - Rp 300.000 (Estimasi 2-4 jam)\n"
                             . "• **Instalasi Kabel LAN**: Rp 50.000 - Rp 250.000 (Estimasi 3-5 jam)\n\n"
                             . "Tim kami siap melayani setting Mikrotik, pembagian bandwidth, hingga perapihan kabel server.",
                    'category' => 'Network',
                    'suggestedAction' => 'Pesan Layanan Jaringan',
                    'relatedService'  => 'Konfigurasi Wi-Fi'
                ];
            }
            if (str_contains($q, 'printer') || $lastTopic === 'printer') {
                return [
                    'reply' => "🖨️ **Informasi Biaya Service Printer:**\n\n"
                             . "• **Estimasi Biaya**: Rp 50.000 - Rp 175.000\n"
                             . "• **Estimasi Durasi**: 1 hari kerja\n"
                             . "• **Layanan**: Cleaning head cartridge, pasang/service infus, reset counter paper jam/blinking.\n\n"
                             . "Ingin kami periksa printer Anda?",
                    'category' => 'Printer',
                    'suggestedAction' => 'Pesan Service Printer',
                    'relatedService'  => 'Service Printer'
                ];
            }

            // General Pricing Summary
            $dbServicesStr = $this->getServicesContext();
            return [
                'reply' => "💰 **Daftar Estimasi Biaya Layanan TKJ Service Hub:**\n\n" . $dbServicesStr . "\n\nSemua perbaikan dikerjakan dengan transparansi biaya sparepart yang diajukan terlebih dahulu.",
                'category' => 'FAQ',
                'suggestedAction' => 'Pesan Jasa',
                'relatedService'  => 'Jasa IT & Konsultasi Teknis'
            ];
        }

        // 6b. Secure Order Code Tracking (e.g. "Bagaimana status ORD-20261009-0001?")
        if (preg_match('/(ORD-\d{8}-\d{4})/i', $q, $matches)) {
            $code = strtoupper($matches[1]);
            try {
                require_once __DIR__ . '/../Models/Order.php';
                $orderModel = new Order();
                $ord = $orderModel->findByCode($code);
                if ($ord) {
                    $statusMap = [
                        'pending' => 'Menunggu Verifikasi Operator',
                        'assigned' => 'Teknisi Ditugaskan',
                        'in_progress' => 'Sedang Dikerjakan Teknisi',
                        'completed' => 'Selesai (QC Passed)',
                        'cancelled' => 'Dibatalkan'
                    ];
                    $stLabel = $statusMap[$ord['status']] ?? ucfirst($ord['status']);
                    return [
                        'reply' => "📦 **Informasi Status Pesanan (#{$code}):**\n\n"
                                 . "• **Layanan**: " . htmlspecialchars($ord['service_name']) . "\n"
                                 . "• **Status**: **{$stLabel}**\n"
                                 . "• **Jadwal Kunjungan**: " . htmlspecialchars($ord['scheduled_date']) . "\n"
                                 . "• **Deskripsi**: " . htmlspecialchars($ord['problem_description']) . "\n\n"
                                 . "Silakan pantau perkembangan lebih rinci melalui menu **Dashboard Pesanan** Anda.",
                        'category' => 'OrderTracking',
                        'suggestedAction' => 'Lihat Detail Pesanan',
                        'relatedService'  => $ord['service_name']
                    ];
                }
            } catch (\Throwable $e) {}

            return [
                'reply' => "⚠️ Kode pesanan **#{$code}** tidak ditemukan di sistem kami. Mohon periksa kembali kode order Anda di dashboard pelanggan.",
                'category' => 'OrderTracking',
                'suggestedAction' => 'Cek Dashboard Pesanan',
                'relatedService'  => 'Layanan Pelanggan'
            ];
        }

        // 7. CCTV Issues & Inquiries ("Apakah melayani pemasangan CCTV?", "Kenapa CCTV saya hanya menampilkan gambar?")
        if (str_contains($q, 'cctv') || str_contains($q, 'dvr') || str_contains($q, 'kamera') || str_contains($q, 'monitor')) {
            if (str_contains($q, 'tidak tampil') || str_contains($q, 'tidak ada gambar') || str_contains($q, 'hanya menampilkan gambar') || str_contains($q, 'blank') || str_contains($q, 'mati') || str_contains($q, 'no signal') || str_contains($q, 'black screen') || str_contains($q, 'kenapa')) {
                return [
                    'reply' => "📹 **Diagnosa Awal Kendala CCTV (Gambar Hilang / tidak tampil):**\n\n"
                             . "Kemungkinan terdapat masalah pada **kabel HDMI/VGA**, **power supply adaptor**, atau **unit DVR/NVR**.\n\n"
                             . "🔍 **Langkah Pengecekan Mandiri yang Dapat Anda Lakukan:**\n"
                             . "1. Pastikan kabel HDMI/VGA terpasang kencang di port DVR dan Monitor.\n"
                             . "2. Cek apakah lampu indikator power pada adaptor DVR menyala.\n"
                             . "3. Pastikan input source pada monitor sudah disetel ke saluran yang sesuai (HDMI/VGA).\n"
                             . "4. Cek apakah kipas internal DVR berputar.\n\n"
                             . "💡 *Jika langkah di atas belum mengatasi masalah, silakan pesan **Layanan Pemeriksaan CCTV** kami. Teknisi siswa TKJ siap datang melakukan pengecekan fisik!*",
                    'category' => 'CCTV',
                    'suggestedAction' => 'Pesan Pemeriksaan CCTV',
                    'relatedService'  => 'Instalasi CCTV'
                ];
            }

            return [
                'reply' => "📹 **Layanan CCTV TKJ Service Hub:**\n\n"
                         . "Ya, tentu! Kami melayani **pemasangan dan perbaikan unit CCTV** (IP Camera & Analog HD).\n\n"
                         . "• **Layanan Kami**:\n"
                         . "  - Pemasangan unit CCTV baru & penataan kabel rapi\n"
                         . "  - Setting Remote Viewing (Pantau CCTV via HP Android/iOS)\n"
                         . "  - Repair & Troubleshooting DVR/NVR (Harddisk error, password reset, kendor kabel)\n"
                         . "• **Rentang Biaya**: Rp 150.000 - Rp 500.000 (Estimasi: 1 hari kerja).\n\n"
                         . "Apakah Anda ingin menjadwalkan kedatangan teknisi CCTV kami?",
                'category' => 'CCTV',
                'suggestedAction' => 'Pesan Service CCTV',
                'relatedService'  => 'Instalasi CCTV'
            ];
        }

        // 8. Wi-Fi / Mikrotik / Network Issues ("Kenapa Wi-Fi saya tidak bisa terhubung?")
        if (str_contains($q, 'wifi') || str_contains($q, 'wi-fi') || str_contains($q, 'mikrotik') || str_contains($q, 'jaringan') || str_contains($q, 'internet') || str_contains($q, 'lan') || str_contains($q, 'router') || str_contains($q, 'terhubung') || str_contains($q, 'koneksi')) {
            return [
                'reply' => "🌐 **Diagnosa Awal & Troubleshooting Jaringan / Wi-Fi:**\n\n"
                         . "Kendala Wi-Fi tidak terhubung atau internet lemot/putus-nyambung umumnya disebabkan oleh:\n"
                         . "• **Frekuensi / Password Wi-Fi**: Terjadi pergantian konfigurasi atau IP conflict.\n"
                         . "• **Router / Access Point Hang**: Penumpukan cache / overload pada Modem ISP atau Mikrotik.\n"
                         . "• **Kabel LAN Terputus**: Kerusakan fisik pada konektor RJ45.\n\n"
                         . "🛠️ **Langkah Penanganan Mandiri:**\n"
                         . "1. Lakukan *Forget Network* di HP/Laptop lalu hubungkan kembali dengan memasukkan password.\n"
                         . "2. Restart Router/Access Point dengan mencabut kabel power selama 30 detik.\n"
                         . "3. Pastikan indikator lampu LAN/WAN di modem menyala normal.\n\n"
                         . "💡 *Tim teknisi kami melayani Konfigurasi Wi-Fi (Rp 75.000 - Rp 300.000) dan Troubleshooting Jaringan (Rp 50.000 - Rp 150.000).*",
                'category' => 'Network',
                'suggestedAction' => 'Pesan Layanan Jaringan',
                'relatedService'  => 'Konfigurasi Wi-Fi'
            ];
        }

        // 9. PC / Laptop / Operating System Issues
        if (str_contains($q, 'laptop') || str_contains($q, 'komputer') || str_contains($q, 'pc') || str_contains($q, 'windows') || str_contains($q, 'blue screen') || str_contains($q, 'mati total') || str_contains($q, 'lambat') || str_contains($q, 'panas')) {
            return [
                'reply' => "💻 **Diagnosa Awal Perangkat PC / Laptop:**\n\n"
                         . "Kendala seperti **laptop mati total, lemot, atau blue screen** bisa dipicu oleh:\n"
                         . "• **Storage (HDD/SSD)**: Bad sector atau ruang penyimpanan hampir habis.\n"
                         . "• **Thermal Paste Kering**: Menyebabkan overheat sehingga laptop sering mati sendiri.\n"
                         . "• **Sistem Operasi Corrupt**: Terkena virus atau gagal update Windows.\n\n"
                         . "🛠️ **Langkah Awal:**\n"
                         . "1. Diamkan laptop dingin terlebih dahulu jika terjadi overheat.\n"
                         . "2. Lepas charger dan tekan tombol power selama 20 detik (Hard Reset).\n"
                         . "3. Pertimbangkan upgrade SSD untuk mendongkrak kecepatan sistem.\n\n"
                         . "💡 *Biaya Service Laptop: Rp 75.000 - Rp 350.000 | Service Komputer: Rp 50.000 - Rp 200.000.*",
                'category' => 'PC/Laptop',
                'suggestedAction' => 'Pesan Service Laptop/PC',
                'relatedService'  => 'Service Laptop'
            ];
        }

        // 10. Printer Issues
        if (str_contains($q, 'printer') || str_contains($q, 'infus') || str_contains($q, 'cartridge') || str_contains($q, 'cetak') || str_contains($q, 'garis') || str_contains($q, 'paper jam') || str_contains($q, 'blinking')) {
            return [
                'reply' => "🖨️ **Troubleshooting Dasar Printer & Sistem Infus:**\n\n"
                         . "Hasil cetak bergaris atau lampu blinking pada printer umumnya terjadi karena:\n"
                         . "• **Head Cartridge Tersumbat**: Tinta mengering akibat jarang digunakan.\n"
                         . "• **Udara di Selang Infus**: Tinta terhambat mengalir ke cartridge.\n"
                         . "• **Waste Ink Pad Full**: Memerlukan reset software / pembersihan absorber.\n\n"
                         . "🛠️ **Langkah Penanganan:**\n"
                         . "1. Jalankan fitur *Head Cleaning* dari maintenance driver printer 1-2 kali.\n"
                         . "2. Cek saluran selang infus dalam keadaan tidak terjepit.\n\n"
                         . "💡 *Biaya Service Printer TKJ Service Hub: Rp 50.000 - Rp 175.000 (Estimasi: 1 hari).*",
                'category' => 'Printer',
                'suggestedAction' => 'Pesan Service Printer',
                'relatedService'  => 'Service Printer'
            ];
        }

        // Handle short follow-up questions using history context
        if (!empty($lastTopic)) {
            if ($lastTopic === 'laptop') {
                return [
                    'reply' => "💻 **Lanjutan Topik Service Laptop:**\n\nLayanan service laptop kami mencakup perbaikan hardware/software, cleaning fan, ganti thermal paste, hingga upgrade SSD/RAM. Biaya berkisar antara **Rp 75.000 - Rp 350.000** dengan estimasi waktu **1-3 hari**.",
                    'category' => 'PC/Laptop',
                    'suggestedAction' => 'Pesan Service Laptop',
                    'relatedService'  => 'Service Laptop'
                ];
            }
            if ($lastTopic === 'cctv') {
                return [
                    'reply' => "📹 **Lanjutan Topik CCTV:**\n\nLayanan CCTV mencakup instalasi unit baru, perbaikan DVR, penataan kabel, dan setting monitoring online via HP. Biaya berkisar **Rp 150.000 - Rp 500.000** (Estimasi: 1 hari).",
                    'category' => 'CCTV',
                    'suggestedAction' => 'Pesan Service CCTV',
                    'relatedService'  => 'Instalasi CCTV'
                ];
            }
        }

        // General Clarification Fallback (No diagnosis lists attached!)
        return [
            'reply' => "Mohon maaf, **TKJ AI Assistant** belum sepenuhnya memahami pertanyaan Anda. Bisa jelaskan lebih rinci kendala perangkat (CCTV, Wi-Fi, Laptop, Printer) atau informasi layanan yang Anda butuhkan?",
            'category' => 'General',
            'suggestedAction' => 'Tanyakan Kendala',
            'relatedService'  => 'Jasa IT & Konsultasi Teknis'
        ];
    }


    /**
     * Local Technician Checklist Helper
     */
    private function getLocalTechnicianChecklist($serviceType, $problemDescription)
    {
        $service = mb_strtolower($serviceType);

        if (str_contains($service, 'cctv')) {
            return "📋 **CHECKLIST DIAGNOSA TEKNISI - CCTV & DVR**\n\n"
                 . "[ ] 1. **Keselamatan Kerja (K3)**: Pastikan arus listrik di stopkontak stabil sebelum menyentuh kabel power.\n"
                 . "[ ] 2. **Uji Arus Adaptor**: Ukur tegangan keluaran adaptor DVR/Kamera dengan Multimeter (Standard 12V DC).\n"
                 . "[ ] 3. **Pemeriksaan Kabel & Port**: Test kontinuitas kabel Coaxial / UTP Balun dan pastikan port BNC/RJ45 tidak korosi.\n"
                 . "[ ] 4. **Pemeriksaan DVR/NVR**: Cek indikator HDD menyala, pastikan firmware tidak bermasalah.\n"
                 . "[ ] 5. **Pengujian Output Display**: Pindah konektor HDMI/VGA ke monitor uji teknisi.";
        }

        if (str_contains($service, 'jaringan') || str_contains($service, 'mikrotik') || str_contains($service, 'wi-fi')) {
            return "📋 **CHECKLIST DIAGNOSA TEKNISI - MIKROTIK & JARINGAN**\n\n"
                 . "[ ] 1. **Pemeriksaan Fisik**: Cek status LED indikator Ethernet & Power pada Routerboard Mikrotik / AP.\n"
                 . "[ ] 2. **Testing Kabel LAN**: Lakukan cable testing RJ45 menggunakan LAN Tester (Pastikan urutan Straight/Cross sesuai standard T568B).\n"
                 . "[ ] 3. **Akses Router**: Cek MAC Address melalui Winbox / WebFig untuk verifikasi keaktifan interface.\n"
                 . "[ ] 4. **Analisis Traffic & Ping**: Uji Ping ke Gateway ISP dan DNS Google (8.8.8.8) untuk memastikan rute internet.\n"
                 . "[ ] 5. **Optimasi Wireless**: Lakukan Frequency Scan untuk menghindari channel Wi-Fi yang berbenturan.";
        }

        if (str_contains($service, 'printer')) {
            return "📋 **CHECKLIST DIAGNOSA TEKNISI - PRINTER & SYSTEM INFUS**\n\n"
                 . "[ ] 1. **Inspeksi Fisik**: Cek apakah ada benda asing (paper clip/kertas tersangkut) di jalur mekanik paper roller.\n"
                 . "[ ] 2. **Saluran Selang Tinta**: Cek ketersediaan tinta tabung luar dan pastikan tidak ada gelembung udara mengunci selang.\n"
                 . "[ ] 3. **Pemeriksaan Cartridge**: Cek PIN kuningan cartridge dan lakukan tes semprot/cleaning mekanis manual.\n"
                 . "[ ] 4. **Nozzle Test**: Cetak lembar Nozzle Check via software driver untuk menilai kerapatan warna.\n"
                 . "[ ] 5. **Reset Counter**: Verifikasi status Waste Ink Pad Counter jika lampu indikator berkedip bergantian.";
        }

        // Default PC/Laptop Checklist
        return "📋 **CHECKLIST DIAGNOSA TEKNISI - SERVICE PC / LAPTOP**\n\n"
             . "[ ] 1. **Diagnosa Power Listrik**: Cek voltase charger laptop/Power Supply PC (Gunakan PSU Tester).\n"
             . "[ ] 2. **Uji RAM & RAM Slots**: Bersihkan pin RAM menggunakan penghapus halus dan coba booting dengan 1 keping RAM.\n"
             . "[ ] 3. **Pemeriksaan Thermal & Kipas**: Cek kebersihan heatsink fan dan periksa kekeringan pasta thermal pada CPU/GPU.\n"
             . "[ ] 4. **Kesehatan Storage (SSD/HDD)**: Cek S.M.A.R.T status dan bad sector menggunakan HDD Sentinel / CrystalDiskInfo.\n"
             . "[ ] 5. **Pengujian OS & Driver**: Uji kestabilan sistem setelah instalasi/perbaikan dengan tes stress ringan (Benchmark).";
    }
}
