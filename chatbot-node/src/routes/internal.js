'use strict';

const express = require('express');
const internalAuth = require('../middleware/internalAuth');
const phpApi = require('../services/phpApi');

const router = express.Router();

router.get('/auth-check', internalAuth, (req, res) => {
  res.json({
    success: true,
    data: {
      service: 'node-chatbot',
      authenticated: true,
      user_id: req.aiUser.userId,
      role: req.aiUser.role,
      scope: req.aiUser.scope,
      read_only: true,
      server_time: new Date().toISOString(),
    },
  });
});

router.get('/php-context', internalAuth, async (req, res, next) => {
  try {
    const phpResponse = await phpApi.getContext(req.aiUser);
    res.json({
      success: true,
      data: {
        node_user: {
          user_id: req.aiUser.userId,
          role: req.aiUser.role,
          scope: req.aiUser.scope,
        },
        php: phpResponse.data || null,
      },
    });
  } catch (error) {
    next(error);
  }
});

module.exports = router;
