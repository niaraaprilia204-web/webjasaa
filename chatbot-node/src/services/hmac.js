'use strict';

const crypto = require('crypto');

const VERSION = 1;
const MAX_TOKEN_LENGTH = 8192;
const CLOCK_SKEW_SECONDS = 15;
const MIN_SECRET_LENGTH = 32;

function authError(message, statusCode = 401) {
  const error = new Error(message);
  error.statusCode = statusCode;
  return error;
}

function base64UrlEncode(value) {
  return Buffer.from(value).toString('base64url');
}

function base64UrlDecode(value) {
  if (typeof value !== 'string' || !/^[A-Za-z0-9_-]+$/.test(value)) {
    return null;
  }

  const decoded = Buffer.from(value, 'base64url');
  if (decoded.toString('base64url') !== value) {
    return null;
  }
  return decoded;
}

function safeClaim(value, maxLength = 160) {
  if (typeof value !== 'string') {
    return '';
  }
  // eslint-disable-next-line no-control-regex
  const cleaned = value.replace(/[\u0000-\u001f\u007f]/g, '').trim();
  return cleaned.slice(0, maxLength);
}

function issueToken(options, secret) {
  const issuer = String(options.issuer || '');
  const audience = String(options.audience || '');
  const role = String(options.role || '').toLowerCase();
  const scope = String(options.scope || role).toLowerCase();
  const subject = String(options.sub || '');
  const ttl = Math.min(300, Math.max(30, Number(options.ttlSeconds) || 60));

  if (!secret || secret.length < MIN_SECRET_LENGTH) {
    throw authError('AI_INTERNAL_SECRET is not configured.', 503);
  }
  if (!/^[1-9][0-9]{0,19}$/.test(subject)) {
    throw authError('Invalid token subject.', 403);
  }
  if (!['customer', 'admin'].includes(role) || scope !== role) {
    throw authError('Only customer and admin tokens are allowed.', 403);
  }

  const now = Math.floor(Date.now() / 1000);
  const payload = {
    v: VERSION,
    iss: issuer,
    aud: audience,
    sub: subject,
    role,
    scope,
    // Identity claims are signed by the shared secret and originate from the
    // PHP session, never from the user's chat message.
    name: safeClaim(options.name || ''),
    email: safeClaim(options.email || ''),
    username: safeClaim(options.username || ''),
    iat: now,
    exp: now + ttl,
    nonce: crypto.randomBytes(16).toString('hex'),
  };

  const encodedPayload = base64UrlEncode(JSON.stringify(payload));
  const signature = crypto.createHmac('sha256', secret).update(encodedPayload).digest();

  return `${encodedPayload}.${base64UrlEncode(signature)}`;
}

function verifyToken(token, options) {
  const issuer = String(options.issuer || '');
  const audience = String(options.audience || '');
  const secret = String(options.secret || '');

  if (secret.length < MIN_SECRET_LENGTH) {
    throw authError('AI_INTERNAL_SECRET is not configured.', 503);
  }

  const raw = String(token || '').trim();
  if (raw === '' || raw.length > MAX_TOKEN_LENGTH) {
    throw authError('Invalid internal token.');
  }

  const parts = raw.split('.');
  if (parts.length !== 2) {
    throw authError('Invalid internal token format.');
  }

  const providedSignature = base64UrlDecode(parts[1]);
  if (!providedSignature || providedSignature.length !== 32) {
    throw authError('Invalid internal token signature.');
  }

  const expectedSignature = crypto.createHmac('sha256', secret).update(parts[0]).digest();
  if (!crypto.timingSafeEqual(expectedSignature, providedSignature)) {
    throw authError('Internal token signature mismatch.');
  }

  const payloadBuffer = base64UrlDecode(parts[0]);
  if (!payloadBuffer) {
    throw authError('Invalid internal token payload.');
  }

  let claims;
  try {
    claims = JSON.parse(payloadBuffer.toString('utf8'));
  } catch (error) {
    throw authError('Internal token payload is not valid JSON.');
  }

  if (!claims || typeof claims !== 'object') {
    throw authError('Invalid internal token payload.');
  }

  const role = String(claims.role || '').toLowerCase();
  const scope = String(claims.scope || '').toLowerCase();
  const subject = String(claims.sub || '');
  const nonce = String(claims.nonce || '');
  const issuedAt = Number(claims.iat || 0);
  const expiresAt = Number(claims.exp || 0);
  const now = Math.floor(Date.now() / 1000);

  if (Number(claims.v) !== VERSION || claims.iss !== issuer || claims.aud !== audience) {
    throw authError('Internal token issuer or audience mismatch.');
  }
  if (!/^[1-9][0-9]{0,19}$/.test(subject)) {
    throw authError('Invalid internal token subject.');
  }
  if (!['customer', 'admin'].includes(role) || scope !== role) {
    throw authError('Internal token role is not allowed.', 403);
  }
  if (!/^[a-f0-9]{32,128}$/.test(nonce)) {
    throw authError('Invalid internal token nonce.');
  }
  if (!Number.isInteger(issuedAt) || !Number.isInteger(expiresAt) || issuedAt < 1 || expiresAt <= issuedAt) {
    throw authError('Invalid internal token lifetime.');
  }
  if (issuedAt > now + CLOCK_SKEW_SECONDS) {
    throw authError('Internal token was issued in the future.');
  }
  if (expiresAt < now - CLOCK_SKEW_SECONDS) {
    throw authError('Internal token has expired.');
  }

  return {
    userId: Number(subject),
    role,
    scope,
    name: safeClaim(claims.name || ''),
    email: safeClaim(claims.email || ''),
    username: safeClaim(claims.username || ''),
    issuer,
    audience,
    issuedAt,
    expiresAt,
    nonce,
  };
}

module.exports = {
  issueToken,
  verifyToken,
};
