'use strict';

const config = require('../config');
const { issueToken } = require('./hmac');

const REQUEST_TIMEOUT_MS = 5000;

function serviceToken(aiUser) {
  return issueToken({
    issuer: 'chatbot-node',
    audience: 'php-internal',
    sub: String(aiUser.userId),
    role: aiUser.role,
    scope: aiUser.scope,
    ttlSeconds: 60,
  }, config.secret);
}

async function requestPhp(path, aiUser, options = {}) {
  if (!/^\/api\/internal\/ai\/[a-z0-9/_-]*(?:\?[a-z0-9_=&%.-]*)?$/i.test(path)) {
    const error = new Error('Invalid internal PHP API path.');
    error.statusCode = 500;
    throw error;
  }

  const method = options.method || 'GET';
  const body = options.body || null;

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

  try {
    const token = serviceToken(aiUser);
    const headers = {
      'Accept': 'application/json',
      'Authorization': `Bearer ${token}`,
      'X-AI-Token': token,
    };

    if (body && method !== 'GET') {
      headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(`${config.phpInternalUrl}${path}`, {
      method,
      headers,
      ...(body && method !== 'GET' ? { body: JSON.stringify(body) } : {}),
      signal: controller.signal,
    });

    const raw = await response.text();
    let bodyParsed;
    try {
      bodyParsed = JSON.parse(raw);
    } catch (error) {
      const invalid = new Error('PHP internal API returned a non-JSON response.');
      invalid.statusCode = 502;
      throw invalid;
    }

    if (!response.ok) {
      const failure = new Error(
        typeof bodyParsed.message === 'string' && bodyParsed.message.length <= 200
          ? bodyParsed.message
          : 'PHP internal API request failed.'
      );
      failure.statusCode = response.status >= 400 && response.status < 600 ? response.status : 502;
      throw failure;
    }

    return bodyParsed;
  } catch (error) {
    if (error.name === 'AbortError') {
      const timeout = new Error('PHP internal API request timed out.');
      timeout.statusCode = 504;
      throw timeout;
    }
    throw error;
  } finally {
    clearTimeout(timer);
  }
}

async function getContext(aiUser) {
  return requestPhp('/api/internal/ai/context', aiUser);
}

async function getOrders(aiUser, filters = {}) {
  const params = new URLSearchParams();
  if (filters.limit !== undefined) {
    params.set('limit', String(filters.limit));
  }
  if (filters.status) {
    params.set('status', String(filters.status));
  }

  const suffix = params.toString() ? `?${params.toString()}` : '';
  return requestPhp(`/api/internal/ai/orders${suffix}`, aiUser);
}

async function getCustomerProfile(aiUser) {
  return requestPhp('/api/internal/ai/customer/profile', aiUser);
}

async function getCustomerOrders(aiUser, filters = {}) {
  const params = new URLSearchParams();
  if (filters.limit !== undefined) {
    params.set('limit', String(filters.limit));
  }
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return requestPhp(`/api/internal/ai/customer/orders${suffix}`, aiUser);
}

async function getServicesCatalog(aiUser) {
  return requestPhp('/api/internal/ai/services', aiUser);
}

/**
 * Katalog layanan + harga (view=catalog|prices, optional keyword/category).
 */
async function getServices(aiUser, filters = {}) {
  const params = new URLSearchParams();
  if (filters.view) {
    params.set('view', String(filters.view));
  }
  if (filters.keyword) {
    params.set('keyword', String(filters.keyword));
  }
  if (filters.category) {
    params.set('category', String(filters.category));
  }
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return requestPhp(`/api/internal/ai/data/services${suffix}`, aiUser);
}

/**
 * Identitas akun yang sedang login (diambil dari database oleh PHP).
 */
async function getIdentity(aiUser) {
  return requestPhp('/api/internal/ai/data/identity', aiUser);
}

/**
 * Informasi publik layanan (kategori, alur pemesanan, alur pembayaran).
 */
async function getPublicInfo(aiUser) {
  return requestPhp('/api/internal/ai/data/public-info', aiUser);
}

/**
 * Pesanan milik customer yang sedang login (dibatasi token bertanda tangan).
 */
async function getMyOrders(aiUser, filters = {}) {
  const params = new URLSearchParams();
  if (filters.limit !== undefined) {
    params.set('limit', String(filters.limit));
  }
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return requestPhp(`/api/internal/ai/data/my-orders${suffix}`, aiUser);
}

/**
 * Detail satu pesanan berdasarkan kode (ORD-YYYYMMDD-XXXX).
 */
async function getOrderDetail(aiUser, orderCode) {
  return requestPhp(
    `/api/internal/ai/data/order-detail?code=${encodeURIComponent(String(orderCode))}`,
    aiUser
  );
}

async function getAdminTotals(aiUser) {
  return requestPhp('/api/internal/ai/data/admin/totals', aiUser);
}

async function getAdminUsers(aiUser, filters = {}) {
  const params = new URLSearchParams();
  if (filters.role) {
    params.set('role', String(filters.role));
  }
  if (filters.keyword) {
    params.set('keyword', String(filters.keyword));
  }
  if (filters.limit !== undefined) {
    params.set('limit', String(filters.limit));
  }
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return requestPhp(`/api/internal/ai/data/admin/users${suffix}`, aiUser);
}

async function getAdminReports(aiUser, filters = {}) {
  const params = new URLSearchParams();
  if (filters.status) {
    params.set('status', String(filters.status));
  }
  if (filters.limit !== undefined) {
    params.set('limit', String(filters.limit));
  }
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return requestPhp(`/api/internal/ai/data/admin/reports${suffix}`, aiUser);
}

async function getMyReports(aiUser, filters = {}) {
  const params = new URLSearchParams();
  if (filters.limit !== undefined) {
    params.set('limit', String(filters.limit));
  }
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return requestPhp(`/api/internal/ai/data/my-reports${suffix}`, aiUser);
}

/**
 * Non-secret AI configuration stored in the database by the Admin AI
 * Configuration page. Contains the model parameters, system knowledge, and
 * editable prompt text. NEVER contains the OpenRouter API key.
 */
async function getAiConfig(aiUser) {
  return requestPhp('/api/internal/ai/data/config', aiUser);
}

async function getAdminCustomers(aiUser, filters = {}) {
  const params = new URLSearchParams();
  if (filters.limit !== undefined) {
    params.set('limit', String(filters.limit));
  }
  if (filters.keyword) {
    params.set('keyword', String(filters.keyword));
  }
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return requestPhp(`/api/internal/ai/data/admin/customers${suffix}`, aiUser);
}

async function getAdminOrders(aiUser, filters = {}) {
  const params = new URLSearchParams();
  if (filters.period) {
    params.set('period', String(filters.period));
  }
  if (filters.status) {
    params.set('status', String(filters.status));
  }
  if (filters.service_id) {
    params.set('service_id', String(filters.service_id));
  }
  if (filters.limit !== undefined) {
    params.set('limit', String(filters.limit));
  }
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return requestPhp(`/api/internal/ai/data/admin/orders${suffix}`, aiUser);
}

async function getAdminOrdersSummary(aiUser, filters = {}) {
  const params = new URLSearchParams();
  if (filters.group_by) {
    params.set('group_by', String(filters.group_by));
  }
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return requestPhp(`/api/internal/ai/data/admin/orders-summary${suffix}`, aiUser);
}

async function getAdminPayments(aiUser) {
  return requestPhp('/api/internal/ai/data/admin/payments', aiUser);
}

async function getAdminTechnicians(aiUser) {
  return requestPhp('/api/internal/ai/data/admin/technicians', aiUser);
}

async function getAdminPopularServices(aiUser, filters = {}) {
  const params = new URLSearchParams();
  if (filters.limit !== undefined) {
    params.set('limit', String(filters.limit));
  }
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return requestPhp(`/api/internal/ai/data/admin/popular-services${suffix}`, aiUser);
}

async function getAdminPeriodComparison(aiUser) {
  return requestPhp('/api/internal/ai/data/admin/period-comparison', aiUser);
}

async function getAdminSchema(aiUser, filters = {}) {
  const params = new URLSearchParams();
  if (filters.table) {
    params.set('table', String(filters.table));
  }
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return requestPhp(`/api/internal/ai/data/admin/schema${suffix}`, aiUser);
}

async function getAdminActivity(aiUser, filters = {}) {
  const params = new URLSearchParams();
  if (filters.days !== undefined) {
    params.set('days', String(filters.days));
  }
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return requestPhp(`/api/internal/ai/data/admin/activity${suffix}`, aiUser);
}

async function getAdminStats(aiUser) {
  return requestPhp('/api/internal/ai/admin/stats', aiUser);
}

async function getAdminActivitySummary(aiUser) {
  return requestPhp('/api/internal/ai/admin/activity-summary', aiUser);
}

/**
 * Panggil endpoint PHP internal generik (dipakai oleh tool calling).
 */
async function callPhp(path, aiUser, options = {}) {
  return requestPhp(path, aiUser, options);
}

module.exports = {
  getContext,
  getOrders,
  getCustomerProfile,
  getCustomerOrders,
  getServicesCatalog,
  getAdminStats,
  getAdminActivitySummary,
  getServices,
  getIdentity,
  getPublicInfo,
  getMyOrders,
  getOrderDetail,
  getMyReports,
  getAiConfig,
  getAdminTotals,
  getAdminUsers,
  getAdminReports,
  getAdminCustomers,
  getAdminOrders,
  getAdminOrdersSummary,
  getAdminPayments,
  getAdminTechnicians,
  getAdminPopularServices,
  getAdminPeriodComparison,
  getAdminSchema,
  getAdminActivity,
  callPhp,
};
