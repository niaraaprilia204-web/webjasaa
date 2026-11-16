'use strict';

const express = require('express');
const helmet = require('helmet');
const config = require('./config');
const internalRoutes = require('./routes/internal');
const internalAuth = require('./middleware/internalAuth');
const { chatWithOpenRouter } = require('./services/openrouterChat');
const { configurationStatus } = require('./services/openrouter');

const app = express();

app.disable('x-powered-by');
app.set('trust proxy', false);
app.use(helmet());
app.use(express.json({ limit: '16kb' }));
app.use((req, res, next) => {
  res.set('Cache-Control', 'no-store, no-cache, must-revalidate');
  res.set('X-Content-Type-Options', 'nosniff');
  next();
});

app.use('/api/internal', internalRoutes);

/**
 * Public health probe (no authentication, no secrets).
 * Used by the Admin AI Configuration dashboard to show Node.js and
 * OpenRouter readiness. It never returns the API key.
 */
app.get('/health', (req, res) => {
  const status = configurationStatus();
  res.json({
    success: true,
    service: 'tkj-chatbot-node',
    status: 'ok',
    time: new Date().toISOString(),
    node: {
      uptime_seconds: Math.floor(process.uptime()),
      version: process.version,
    },
    openrouter: {
      configured: status.configured,
      missing: status.missing,
      model: status.model,
      base_url: status.base_url,
      timeout_ms: status.timeout_ms,
    },
    security: {
      read_only: true,
      api_key_exposed: false,
    },
  });
});

/**
 * Chat endpoint (Node.js + OpenRouter).
 * Dipakai oleh PHP proxy (/ai/chat/node).
 * Memerlukan handoff token HMAC dari PHP.
 */
app.post('/api/chat', internalAuth, async (req, res, next) => {
  try {
    const { query, history = [] } = req.body || {};

    if (!query || typeof query !== 'string' || query.trim() === '') {
      return res.status(422).json({
        success: false,
        message: 'Pertanyaan tidak boleh kosong.',
      });
    }

    if (query.length > 2000) {
      return res.status(422).json({
        success: false,
        message: 'Pertanyaan terlalu panjang. Maksimal 2.000 karakter.',
      });
    }

    const cleanHistory = [];
    for (const msg of history) {
      if (!msg || typeof msg !== 'object') continue;
      const role = String(msg.role || '').toLowerCase();
      const content = typeof msg.content === 'string' ? msg.content.trim() : '';
      if (!content || !['user', 'human', 'assistant', 'model'].includes(role)) continue;
      cleanHistory.push({ role, content });
      if (cleanHistory.length >= 20) break;
    }

    const messages = [
      ...cleanHistory,
      { role: 'user', content: query.trim() },
    ];

    const result = await chatWithOpenRouter({
      messages,
      role: req.aiUser.role,
      userId: req.aiUser.userId,
      // Identitas berasal dari token HMAC bertanda tangan (session PHP).
      identity: {
        name: req.aiUser.name || '',
        email: req.aiUser.email || '',
        username: req.aiUser.username || '',
      },
    });

    return res.json({
      success: true,
      source: 'openrouter',
      data: {
        reply: result.reply,
        model: result.model || null,
        usage: result.usage || null,
        toolUsed: result.toolUsed || false,
        toolsUsed: result.toolsUsed || [],
      },
    });
  } catch (error) {
    next(error);
  }
});

app.use((req, res) => {
  res.status(404).json({
    success: false,
    message: 'Endpoint not found.',
  });
});

app.use((error, req, res, next) => {
  if (res.headersSent) {
    return next(error);
  }

  // OpenRouter errors carry safe, user-facing messages (key is never included).
  if (error.name === 'OpenRouterError' && error.message) {
    return res.status(error.statusCode || 502).json({
      success: false,
      code: error.code || 'OPENROUTER_ERROR',
      message: error.message,
    });
  }

  // OpenRouter configuration errors are safe to surface (no key leaked).
  if (error.code === 'OPENROUTER_NOT_CONFIGURED') {
    return res.status(503).json({
      success: false,
      message: error.message,
    });
  }

  const status = [400, 401, 403, 404, 405, 422, 500, 502, 503, 504].includes(error.statusCode)
    ? error.statusCode
    : 500;

  return res.status(status).json({
    success: false,
    message: status >= 500 ? 'Internal AI service error.' : 'Request rejected.',
  });
});

if (require.main === module) {
  const server = app.listen(config.port, config.host, () => {
    console.log(`TKJ chatbot Node.js listening on http://${config.host}:${config.port}`);
  });

  server.on('error', (error) => {
    if (error.code === 'EADDRINUSE') {
      console.error(`Port ${config.port} is already in use.`);
    } else {
      console.error('Node.js server failed to start.');
    }
    process.exitCode = 1;
  });
}

module.exports = app;
