'use strict';

/**
 * OpenRouter configuration check.
 *
 *   node scripts/check-openrouter.js
 *   node scripts/check-openrouter.js --live
 *
 * Without --live this only checks environment variables and makes no network
 * request. With --live it sends a tiny test request to OpenRouter.
 */
const path = require('path');
const config = require('../src/config');
const { configurationStatus, chatCompletion } = require('../src/services/openrouter');

const live = process.argv.includes('--live');
const envPath = path.join(__dirname, '..', '.env');
const status = configurationStatus();

function printConfiguration() {
  console.log('OpenRouter configuration');
  console.log(`  env_file       : ${envPath}`);
  console.log(`  key_configured : ${config.openRouterApiKey !== '' ? 'yes' : 'no'}`);
  console.log(`  model          : ${status.model || '<empty>'}`);
  console.log(`  base_url       : ${status.base_url}`);
  console.log(`  timeout_ms     : ${status.timeout_ms}`);
  console.log(`  ready          : ${status.configured ? 'yes' : 'no'}`);
}

(async () => {
  printConfiguration();

  if (!status.configured) {
    console.error('');
    console.error(`Belum siap. Isi ${status.missing.join(' dan ')} di:`);
    console.error(`  ${envPath}`);
    console.error('Jangan gunakan API key contoh atau palsu.');
    process.exitCode = 2;
    return;
  }

  if (!live) {
    console.log('');
    console.log('Konfigurasi berhasil dibaca. Untuk tes koneksi nyata, jalankan:');
    console.log('  node scripts/check-openrouter.js --live');
    return;
  }

  try {
    const result = await chatCompletion({
      messages: [
        { role: 'user', content: 'Balas hanya dengan kata: OK' },
      ],
      temperature: 0,
      maxTokens: 12,
      timeoutMs: 30000,
    });

    console.log('');
    console.log('Tes koneksi OpenRouter berhasil.');
    console.log(`  model  : ${result.model || status.model}`);
    console.log(`  reply  : ${result.text}`);
    if (result.usage) {
      console.log(`  usage  : ${JSON.stringify(result.usage)}`);
    }
  } catch (error) {
    console.error('');
    console.error('Tes koneksi OpenRouter gagal.');
    console.error(`  code   : ${error.code || 'OPENROUTER_ERROR'}`);
    console.error(`  message: ${error.message}`);
    process.exitCode = 1;
  }
})();
