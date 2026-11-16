<?php

/**
 * Web Application Route Definitions
 */

// Public Pages & Customer Catalog
Router::get('/', 'Home@index');
Router::get('/about', 'Home@about');
Router::get('/services', 'Home@services');
Router::get('/services/detail', 'Home@serviceDetail');
Router::get('/contact', 'Home@contact');

// Authentication Routes
Router::get('/auth/login', 'Auth@login');
Router::post('/auth/loginProcess', 'Auth@loginProcess');
Router::get('/auth/register', 'Auth@register');
Router::post('/auth/registerProcess', 'Auth@registerProcess');
Router::get('/auth/logout', 'Auth@logout');

// WhatsApp OTP Phone Verification
Router::get('/otp', 'Otp@index');
Router::post('/otp/send', 'Otp@send');
Router::post('/otp/verify', 'Otp@verify');

// Guarded Role Dashboards
Router::get('/customer/dashboard', 'CustomerController@dashboard');
Router::get('/technician/dashboard', 'TechnicianController@dashboard');
Router::get('/technician/jobs/detail', 'TechnicianController@jobDetail');
Router::post('/technician/jobs/update', 'TechnicianController@jobUpdate');
Router::post('/technician/jobs/upload', 'TechnicianController@jobUpload');
Router::get('/operator/dashboard', 'OperatorController@dashboard');
Router::get('/principal/dashboard', 'PrincipalController@dashboard');

// Customer Service Request & Order Routes
Router::get('/customer/orders/create', 'OrderController@create');
Router::post('/customer/orders/store', 'OrderController@store');
Router::get('/customer/orders', 'OrderController@index');
Router::get('/customer/orders/detail', 'OrderController@detail');
Router::post('/customer/orders/review', 'OrderController@submitReview');

// Customer Reports (laporan / komplain)
Router::get('/customer/reports', 'ReportController@index');
Router::get('/customer/reports/create', 'ReportController@create');
Router::post('/customer/reports/store', 'ReportController@store');

// Operator Operational Management - Orders & Assignment
Router::get('/operator/orders', 'OrderController@operatorIndex');
Router::get('/operator/orders/detail', 'OrderController@operatorDetail');
Router::post('/operator/orders/updateDetails', 'OrderController@updateOrderDetails');
Router::post('/operator/orders/updatePayment', 'OrderController@updatePayment');
Router::post('/operator/orders/assignTechnician', 'OrderController@assignTechnician');
Router::post('/operator/orders/updateStatus', 'OrderController@updateStatus');

// Operator Operational Management - Customers
Router::get('/operator/customers', 'CustomerManagementController@index');

// Operator Operational Management - Technicians
Router::get('/operator/technicians', 'TechnicianManagementController@index');
Router::post('/operator/technicians/store', 'TechnicianManagementController@store');
Router::post('/operator/technicians/update', 'TechnicianManagementController@update');

// Operator Master Data Management - Categories
Router::get('/operator/categories', 'CategoryController@index');
Router::post('/operator/categories/store', 'CategoryController@store');
Router::post('/operator/categories/update', 'CategoryController@update');
Router::get('/operator/categories/delete', 'CategoryController@delete');

// Operator Master Data Management - Services
Router::get('/operator/services', 'ServiceController@index');
Router::post('/operator/services/store', 'ServiceController@store');
Router::post('/operator/services/update', 'ServiceController@update');
Router::get('/operator/services/toggle', 'ServiceController@toggle');
Router::get('/operator/services/delete', 'ServiceController@delete');

// Administrator Dashboard - Users, Reports, Chat History, Catalog
Router::get('/admin/dashboard', 'AdminController@dashboard');
Router::get('/admin/users', 'AdminController@users');
Router::post('/admin/users/create', 'AdminController@createUser');
Router::post('/admin/users/update', 'AdminController@updateUser');
Router::post('/admin/users/delete', 'AdminController@deleteUser');
Router::get('/admin/reports', 'AdminController@reports');
Router::get('/admin/chat-history', 'AdminController@chatHistory');
Router::get('/admin/whatsapp', 'AdminController@whatsapp');
Router::post('/admin/whatsapp/resend', 'AdminController@resendWhatsappNotification');
Router::get('/admin/customer-reports', 'AdminController@customerReports');
Router::post('/admin/customer-reports/status', 'AdminController@updateCustomerReportStatus');

// Admin AI Configuration (strictly admin-only)
Router::get('/admin/ai-config', 'AiConfigController@index');
Router::get('/admin/ai-config/health', 'AiConfigController@health');
Router::post('/admin/ai-config/system-knowledge/save', 'AiConfigController@saveSystemKnowledge');
Router::post('/admin/ai-config/system-knowledge/delete', 'AiConfigController@deleteSystemKnowledge');
Router::post('/admin/ai-config/prompt/save', 'AiConfigController@savePrompt');
Router::post('/admin/ai-config/prompt/reset', 'AiConfigController@resetPrompt');
Router::post('/admin/ai-config/model/save', 'AiConfigController@saveModel');
Router::post('/admin/ai-config/api-key/save', 'AiConfigController@saveApiKey');
Router::post('/admin/ai-config/api-key/clear', 'AiConfigController@clearApiKey');
Router::post('/admin/ai-config/test', 'AiConfigController@testAi');
Router::get('/admin/services', 'AdminController@services');
Router::post('/admin/services/store', 'AdminController@storeService');
Router::post('/admin/services/update', 'AdminController@updateService');
Router::post('/admin/services/toggle', 'AdminController@toggleService');
Router::post('/admin/services/delete', 'AdminController@deleteService');

// Operator Dashboard - Reviews, AI Config, Chat/Complaint History, Reports
Router::get('/operator/reviews', 'OperatorController@reviews');
Router::get('/operator/ai-config', 'OperatorController@aiConfig');
Router::post('/operator/ai-config/update', 'OperatorController@updateAiConfig');
Router::get('/operator/chat-history', 'OperatorController@chatHistory');
Router::get('/operator/reports', 'OperatorController@reports');

// Authenticated Profile
Router::get('/profile', 'ProfileController@index');
Router::post('/profile/update', 'ProfileController@update');
Router::post('/profile/password', 'ProfileController@password');

// Notifications
Router::post('/notifications/read-all', 'NotificationController@markAllRead');

// AI Integration Endpoints (Customer Chat & Technician/Operator Helper)
Router::post('/ai/chat', 'AiController@chat');
Router::post('/ai/chat/node', 'AiController@chatNode');
Router::post('/ai/generateDescription', 'AiController@generateDescription');
Router::post('/ai/technicianHelper', 'AiController@technicianHelper');

// Internal AI Bridge (Node.js -> PHP, HMAC-only, read-only)
Router::get('/api/internal/ai/health', 'AiInternalController@health');
Router::get('/api/internal/ai/context', 'AiInternalController@context');
Router::get('/api/internal/ai/orders', 'AiInternalController@orders');
Router::get('/api/internal/ai/customer/profile', 'AiInternalController@customerProfile');
Router::get('/api/internal/ai/customer/orders', 'AiInternalController@customerOrders');
Router::get('/api/internal/ai/services', 'AiInternalController@services');
Router::get('/api/internal/ai/admin/stats', 'AiInternalController@adminStats');
Router::get('/api/internal/ai/admin/activity-summary', 'AiInternalController@adminActivitySummary');

// Internal AI Data Retrieval (Node.js -> PHP, HMAC-only, read-only)
Router::get('/api/internal/ai/data/identity', 'AiDataController@identity');
Router::get('/api/internal/ai/data/public-info', 'AiDataController@publicInfo');
Router::get('/api/internal/ai/data/services', 'AiDataController@services');
Router::get('/api/internal/ai/data/my-orders', 'AiDataController@myOrders');
Router::get('/api/internal/ai/data/order-detail', 'AiDataController@orderDetail');
Router::get('/api/internal/ai/data/my-reports', 'AiDataController@myReports');
Router::get('/api/internal/ai/data/config', 'AiDataController@config');
Router::get('/api/internal/ai/data/admin/totals', 'AiDataController@totals');
Router::get('/api/internal/ai/data/admin/users', 'AiDataController@adminUsers');
Router::get('/api/internal/ai/data/admin/customers', 'AiDataController@customers');
Router::get('/api/internal/ai/data/admin/reports', 'AiDataController@adminReports');
Router::get('/api/internal/ai/data/admin/orders', 'AiDataController@orders');
Router::get('/api/internal/ai/data/admin/orders-summary', 'AiDataController@ordersSummary');
Router::get('/api/internal/ai/data/admin/payments', 'AiDataController@payments');
Router::get('/api/internal/ai/data/admin/technicians', 'AiDataController@technicians');
Router::get('/api/internal/ai/data/admin/popular-services', 'AiDataController@popularServices');
Router::get('/api/internal/ai/data/admin/period-comparison', 'AiDataController@periodComparison');
Router::get('/api/internal/ai/data/admin/schema', 'AiDataController@schema');
Router::get('/api/internal/ai/data/admin/activity', 'AiDataController@activity');

