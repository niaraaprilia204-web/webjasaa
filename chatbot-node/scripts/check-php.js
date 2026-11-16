'use strict';

/**
 * Stage 3 Node -> PHP authentication check.
 *
 * Usage:
 *   node scripts/check-php.js customer 18
 *   node scripts/check-php.js admin 15
 */
const config = require('../src/config');
const { issueToken } = require('../src/services/hmac');

const role = String(process.argv[2] || 'customer').toLowerCase();
const userId = String(process.argv[3] || '18');

if (!['customer', 'admin'].includes(role) || !/^[1-9][0-9]{0,19}$/.test(userId)) {
  console.error('Usage: node scripts/check-php.js <customer|admin> <user_id>');
  process.exit(1);
}

function token() {
  return issueToken({
    issuer: 'chatbot-node',
    audience: 'php-internal',
    sub: userId,
    role,
    scope: role,
    ttlSeconds: 60,
  }, config.secret);
}

async function call(path) {
  const signed = token();
  const response = await fetch(`${config.phpInternalUrl}${path}`, {
    headers: {
      'Accept': 'application/json',
      'Authorization': `Bearer ${signed}`,
      'X-AI-Token': signed,
    },
  });

  const text = await response.text();
  let body;
  try {
    body = JSON.parse(text);
  } catch (error) {
    body = { raw: text.slice(0, 500) };
  }

  console.log(JSON.stringify({
    endpoint: path,
    http_status: response.status,
    body,
  }, null, 2));
}

(async () => {
  await call('/api/internal/ai/context');
  await call('/api/internal/ai/orders?limit=5');
})();
