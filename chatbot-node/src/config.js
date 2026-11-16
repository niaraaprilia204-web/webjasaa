'use strict';

const path = require('path');
const dotenv = require('dotenv');

dotenv.config({ path: path.join(__dirname, '..', '.env') });

const secret = String(process.env.AI_INTERNAL_SECRET || '').trim();
if (secret.length < 32) {
  throw new Error('AI_INTERNAL_SECRET must be at least 32 characters. Copy .env.example to .env and fill it.');
}

const port = Number.parseInt(process.env.PORT || '3001', 10);
if (!Number.isInteger(port) || port < 1 || port > 65535) {
  throw new Error('PORT must be an integer between 1 and 65535.');
}

const phpInternalUrl = String(process.env.PHP_INTERNAL_URL || 'http://localhost/webjasa').replace(/\/+$/, '');
if (!/^https?:\/\//i.test(phpInternalUrl)) {
  throw new Error('PHP_INTERNAL_URL must start with http:// or https://');
}

const openRouterApiKey = String(process.env.OPENROUTER_API_KEY || '').trim();
const openRouterModel = String(process.env.OPENROUTER_MODEL || '').trim();
const openRouterBaseUrl = String(
  process.env.OPENROUTER_BASE_URL || 'https://openrouter.ai/api/v1'
).replace(/\/+$/, '');
if (!/^https?:\/\//i.test(openRouterBaseUrl)) {
  throw new Error('OPENROUTER_BASE_URL must start with http:// or https://');
}

const parsedOpenRouterTimeout = Number.parseInt(process.env.OPENROUTER_TIMEOUT_MS || '30000', 10);
const openRouterTimeoutMs = Number.isInteger(parsedOpenRouterTimeout)
  ? Math.min(120000, Math.max(5000, parsedOpenRouterTimeout))
  : 30000;

module.exports = {
  nodeEnv: String(process.env.NODE_ENV || 'development'),
  port,
  host: '127.0.0.1',
  secret,
  phpInternalUrl,
  appUrl: String(process.env.APP_URL || 'http://localhost/webjasa').replace(/\/+$/, ''),
  openRouterApiKey,
  openRouterModel,
  openRouterBaseUrl,
  openRouterTimeoutMs,
  openRouterConfigured: openRouterApiKey !== '' && openRouterModel !== '',
};
