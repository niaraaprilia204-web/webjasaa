'use strict';

const config = require('../config');

const MESSAGE_ROLES = new Set(['system', 'user', 'assistant']);
const MAX_MESSAGES = 100;
const MAX_MESSAGE_LENGTH = 20000;
const MAX_OUTPUT_TOKENS = 8000;

class OpenRouterError extends Error {
  constructor(message, statusCode = 502, code = 'OPENROUTER_ERROR') {
    super(message);
    this.name = 'OpenRouterError';
    this.statusCode = statusCode;
    this.code = code;
  }
}

/**
 * Safe configuration status for logs/health checks. Never returns the key.
 */
function configurationStatus() {
  const missing = [];
  if (!config.openRouterApiKey) {
    missing.push('OPENROUTER_API_KEY');
  }
  if (!config.openRouterModel) {
    missing.push('OPENROUTER_MODEL');
  }

  return {
    configured: missing.length === 0,
    missing,
    model: config.openRouterModel || null,
    base_url: config.openRouterBaseUrl,
    timeout_ms: config.openRouterTimeoutMs,
  };
}

function assertConfigured() {
  const status = configurationStatus();
  if (!status.configured) {
    throw new OpenRouterError(
      `OpenRouter belum dikonfigurasi. Isi ${status.missing.join(' dan ')} di chatbot-node/.env.`,
      503,
      'OPENROUTER_NOT_CONFIGURED'
    );
  }
}

function normalizeMessages(messages) {
  if (!Array.isArray(messages) || messages.length === 0) {
    throw new OpenRouterError(
      'Daftar pesan OpenRouter tidak boleh kosong.',
      422,
      'OPENROUTER_INVALID_MESSAGES'
    );
  }

  if (messages.length > MAX_MESSAGES) {
    throw new OpenRouterError(
      `Daftar pesan OpenRouter maksimal ${MAX_MESSAGES} pesan.`,
      422,
      'OPENROUTER_TOO_MANY_MESSAGES'
    );
  }

  return messages.map((message, index) => {
    if (!message || typeof message !== 'object') {
      throw new OpenRouterError(
        `Pesan OpenRouter ke-${index + 1} tidak valid.`,
        422,
        'OPENROUTER_INVALID_MESSAGE'
      );
    }

    const role = String(message.role || '').toLowerCase();

    // Pesan assistant boleh membawa tool_calls (content boleh null/kosong).
    if (role === 'assistant') {
      const content = typeof message.content === 'string' ? message.content : '';
      if (content.length > MAX_MESSAGE_LENGTH) {
        throw new OpenRouterError(
          `Pesan OpenRouter ke-${index + 1} terlalu panjang.`,
          422,
          'OPENROUTER_MESSAGE_TOO_LONG'
        );
      }

      const normalized = { role: 'assistant' };
      if (content.trim() !== '') {
        normalized.content = content;
      }

      if (Array.isArray(message.tool_calls) && message.tool_calls.length > 0) {
        normalized.tool_calls = message.tool_calls.map((toolCall) => ({
          id: String((toolCall && toolCall.id) || ''),
          type: 'function',
          function: {
            name: String((toolCall && toolCall.function && toolCall.function.name) || ''),
            arguments:
              toolCall && toolCall.function && typeof toolCall.function.arguments === 'string'
                ? toolCall.function.arguments
                : '{}',
          },
        }));
      }

      if (!normalized.content && !normalized.tool_calls) {
        throw new OpenRouterError(
          `Pesan OpenRouter ke-${index + 1} (assistant) tidak memiliki isi maupun tool_calls.`,
          422,
          'OPENROUTER_INVALID_MESSAGE'
        );
      }

      return normalized;
    }

    // Pesan hasil eksekusi tool (function calling result).
    if (role === 'tool') {
      const content = typeof message.content === 'string' ? message.content : '';
      if (content.trim() === '') {
        throw new OpenRouterError(
          `Pesan OpenRouter ke-${index + 1} (tool) harus memiliki isi.`,
          422,
          'OPENROUTER_INVALID_MESSAGE'
        );
      }
      if (content.length > MAX_MESSAGE_LENGTH) {
        throw new OpenRouterError(
          `Pesan OpenRouter ke-${index + 1} terlalu panjang.`,
          422,
          'OPENROUTER_MESSAGE_TOO_LONG'
        );
      }
      const toolCallId = String(message.tool_call_id || '');
      if (toolCallId === '') {
        throw new OpenRouterError(
          `Pesan OpenRouter ke-${index + 1} (tool) harus memiliki tool_call_id.`,
          422,
          'OPENROUTER_INVALID_MESSAGE'
        );
      }
      return { role: 'tool', tool_call_id: toolCallId, content };
    }

    const content = typeof message.content === 'string' ? message.content : '';

    if (!MESSAGE_ROLES.has(role) || content.trim() === '') {
      throw new OpenRouterError(
        `Pesan OpenRouter ke-${index + 1} harus memiliki role system/user/assistant dan isi teks.`,
        422,
        'OPENROUTER_INVALID_MESSAGE'
      );
    }

    if (content.length > MAX_MESSAGE_LENGTH) {
      throw new OpenRouterError(
        `Pesan OpenRouter ke-${index + 1} terlalu panjang.`,
        422,
        'OPENROUTER_MESSAGE_TOO_LONG'
      );
    }

    return { role, content };
  });
}

function clampNumber(value, min, max, fallback) {
  const number = Number(value);
  if (!Number.isFinite(number)) {
    return fallback;
  }
  return Math.min(max, Math.max(min, number));
}

function sanitizeProviderMessage(value) {
  if (typeof value !== 'string') {
    return '';
  }

  const cleaned = value.replace(/\s+/g, ' ').trim().slice(0, 200);
  // Do not echo anything that looks like an OpenRouter key.
  return /sk-or-/i.test(cleaned) ? '' : cleaned;
}

function upstreamError(response, payload, model) {
  const providerMessage = sanitizeProviderMessage(payload && payload.error && payload.error.message);
  const suffix = providerMessage ? ` Detail: ${providerMessage}` : '';

  if (response.status === 401 || response.status === 403) {
    return new OpenRouterError(
      'OpenRouter menolak API key. Periksa OPENROUTER_API_KEY.' + suffix,
      502,
      'OPENROUTER_AUTH_ERROR'
    );
  }

  if (response.status === 404) {
    return new OpenRouterError(
      `Model OpenRouter "${model}" tidak ditemukan. Periksa OPENROUTER_MODEL.` + suffix,
      502,
      'OPENROUTER_MODEL_NOT_FOUND'
    );
  }

  if (response.status === 429) {
    return new OpenRouterError(
      'Batas permintaan OpenRouter tercapai. Coba lagi nanti.' + suffix,
      503,
      'OPENROUTER_RATE_LIMIT'
    );
  }

  if (response.status >= 500) {
    return new OpenRouterError(
      'Layanan OpenRouter sedang bermasalah.' + suffix,
      503,
      'OPENROUTER_UNAVAILABLE'
    );
  }

  return new OpenRouterError(
    `OpenRouter menolak permintaan (HTTP ${response.status}). Periksa model dan format pesan.` + suffix,
    502,
    'OPENROUTER_REQUEST_REJECTED'
  );
}

/**
 * Send a non-streaming chat completion request to OpenRouter.
 *
 * If the key or model is missing, this throws before any network request is
 * made. It never returns a fake answer.
 */
async function chatCompletion(options = {}) {
  const apiKey = String(options.apiKey || config.openRouterApiKey || '').trim();
  if (apiKey === '') {
    throw new OpenRouterError(
      'OpenRouter belum dikonfigurasi. Isi OPENROUTER_API_KEY di chatbot-node/.env '
      + 'atau simpan API key lewat halaman Admin "AI Configuration".',
      503,
      'OPENROUTER_NOT_CONFIGURED'
    );
  }

  const messages = normalizeMessages(options.messages);
  const model = String(options.model || config.openRouterModel).trim();
  if (model === '') {
    throw new OpenRouterError(
      'OPENROUTER_MODEL belum diisi.',
      503,
      'OPENROUTER_NOT_CONFIGURED'
    );
  }

  const temperature = clampNumber(options.temperature, 0, 2, 0.4);
  const maxTokens = Math.floor(
    clampNumber(options.maxTokens, 1, MAX_OUTPUT_TOKENS, 1000)
  );
  const timeoutMs = Math.floor(
    clampNumber(options.timeoutMs, 5000, 120000, config.openRouterTimeoutMs)
  );

  const payload = {
    model,
    messages,
    temperature,
    max_tokens: maxTokens,
    stream: false,
  };

  if (options.responseFormat && typeof options.responseFormat === 'object') {
    payload.response_format = options.responseFormat;
  }

  if (Array.isArray(options.tools) && options.tools.length > 0) {
    payload.tools = options.tools;
  }

  if (options.tool_choice) {
    payload.tool_choice = options.tool_choice;
  }

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);

  let response;
  try {
    response = await fetch(`${config.openRouterBaseUrl}/chat/completions`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${apiKey}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'HTTP-Referer': config.appUrl,
        'X-Title': 'TKJ Service Hub',
      },
      body: JSON.stringify(payload),
      signal: controller.signal,
    });
  } catch (error) {
    if (error.name === 'AbortError') {
      throw new OpenRouterError(
        'Permintaan ke OpenRouter melewati batas waktu.',
        504,
        'OPENROUTER_TIMEOUT'
      );
    }

    throw new OpenRouterError(
      'Tidak dapat menghubungi OpenRouter. Periksa koneksi internet server.',
      502,
      'OPENROUTER_NETWORK_ERROR'
    );
  } finally {
    clearTimeout(timer);
  }

  let raw = '';
  try {
    raw = await response.text();
  } catch (error) {
    throw new OpenRouterError(
      'Gagal membaca respons OpenRouter.',
      502,
      'OPENROUTER_READ_ERROR'
    );
  }

  let body = null;
  if (raw !== '') {
    try {
      body = JSON.parse(raw);
    } catch (error) {
      body = null;
    }
  }

  if (!response.ok) {
    // Free-tier OpenRouter keys can hit "requires more credits" when the
    // max_tokens reservation exceeds the remaining budget (often because of
    // in-flight requests). Retry once with a halved max_tokens before failing.
    const errMessage = body && body.error && body.error.message ? String(body.error.message) : '';
    if (response.status === 402 && /more credits, or fewer max_tokens/i.test(errMessage)
        && maxTokens > 256 && !options._retried) {
      return chatCompletion(Object.assign({}, options, {
        maxTokens: Math.max(256, Math.floor(maxTokens / 2)),
        _retried: true,
      }));
    }
    throw upstreamError(response, body, model);
  }

  if (!body || typeof body !== 'object') {
    throw new OpenRouterError(
      'OpenRouter mengembalikan respons yang tidak valid.',
      502,
      'OPENROUTER_INVALID_RESPONSE'
    );
  }

  const choice = Array.isArray(body.choices) ? body.choices[0] : null;
  const message = choice && choice.message ? choice.message : {};
  const content = typeof message.content === 'string' ? message.content : null;
  const toolCalls = Array.isArray(message.tool_calls) ? message.tool_calls : null;

  // Jika ada tool calls, izinkan content kosong (model mungkin hanya memanggil tool tanpa teks).
  if (!toolCalls && (typeof content !== 'string' || content.trim() === '')) {
    throw new OpenRouterError(
      'OpenRouter mengembalikan jawaban kosong.',
      502,
      'OPENROUTER_EMPTY_RESPONSE'
    );
  }

  return {
    text: content ? content.trim() : '',
    toolCalls,
    model: body.model || model,
    usage: body.usage && typeof body.usage === 'object' ? body.usage : null,
    finish_reason: choice.finish_reason || null,
    response_id: body.id || null,
  };
}

module.exports = {
  OpenRouterError,
  configurationStatus,
  assertConfigured,
  chatCompletion,
};
