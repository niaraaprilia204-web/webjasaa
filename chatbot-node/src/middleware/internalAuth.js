'use strict';

const config = require('../config');
const { verifyToken } = require('../services/hmac');
const nonceStore = require('../services/nonceStore');

function extractToken(req) {
  const authorization = String(req.get('authorization') || '').trim();
  const bearer = authorization.match(/^Bearer\s+(.+)$/i);
  if (bearer) {
    return bearer[1].trim();
  }

  return String(req.get('x-ai-token') || '').trim();
}

module.exports = function internalAuth(req, res, next) {
  try {
    const token = extractToken(req);
    if (!token) {
      return res.status(401).json({
        success: false,
        message: 'Internal authentication token is required.',
      });
    }

    const claims = verifyToken(token, {
      issuer: 'php-web',
      audience: 'node-chatbot',
      secret: config.secret,
    });

    if (!nonceStore.consume(claims.issuer, claims.nonce, claims.expiresAt)) {
      return res.status(401).json({
        success: false,
        message: 'Internal authentication token has already been used.',
      });
    }

    req.aiUser = {
      userId: claims.userId,
      role: claims.role,
      scope: claims.scope,
      // Identity comes from the signed PHP session token (server-side),
      // never from anything the user typed in the chat box.
      name: claims.name || '',
      email: claims.email || '',
      username: claims.username || '',
      expiresAt: claims.expiresAt,
    };

    return next();
  } catch (error) {
    const status = [401, 403, 503].includes(error.statusCode) ? error.statusCode : 401;
    return res.status(status).json({
      success: false,
      message: status === 503
        ? 'Internal AI authentication is not configured.'
        : 'Internal authentication failed.',
    });
  }
};
