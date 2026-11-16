'use strict';

/**
 * In-memory replay guard for tokens received by this Node process.
 * The PHP side has its own file-backed guard for Node -> PHP tokens.
 */
class NonceStore {
  constructor() {
    this.entries = new Map();
    const timer = setInterval(() => this.cleanup(), 60 * 1000);
    if (typeof timer.unref === 'function') {
      timer.unref();
    }
  }

  consume(issuer, nonce, expiresAtSeconds) {
    const key = `${issuer}:${nonce}`;
    const now = Math.floor(Date.now() / 1000);
    const expiry = Math.max(now, Number(expiresAtSeconds) || now);

    const existing = this.entries.get(key);
    if (existing && existing >= now) {
      return false;
    }

    this.entries.set(key, expiry);
    return true;
  }

  cleanup() {
    const now = Math.floor(Date.now() / 1000);
    for (const [key, expiry] of this.entries.entries()) {
      if (expiry < now - 60) {
        this.entries.delete(key);
      }
    }
  }
}

module.exports = new NonceStore();
