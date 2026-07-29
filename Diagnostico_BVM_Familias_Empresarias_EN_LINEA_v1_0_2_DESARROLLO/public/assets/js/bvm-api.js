/* bvm-api.js — cliente de API con CSRF y reintentos. */
(function (global) {
  'use strict';

  var csrfToken = document.querySelector('meta[name="csrf-token"]');
  csrfToken = csrfToken ? csrfToken.getAttribute('content') : '';

  function apiBase() {
    var meta = document.querySelector('meta[name="bvm-base"]');
    return meta ? meta.getAttribute('content').replace(/\/$/, '') : '';
  }

  function post(path, payload, opts) {
    opts = opts || {};
    payload = payload || {};
    return fetch(apiBase() + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify(payload)
    }).then(function (res) {
      return res.json().catch(function () {
        return { ok: false, error: 'Respuesta no válida del servidor.' };
      }).then(function (data) {
        data.__status = res.status;
        return data;
      });
    });
  }

  /** POST con reintentos exponenciales para el autosave (sin duplicar: el
      servidor hace upsert idempotente por participante+pregunta). */
  function postWithRetry(path, payload, maxRetries, onRetry) {
    maxRetries = maxRetries == null ? 3 : maxRetries;
    var attempt = 0;
    function run() {
      return post(path, payload).catch(function (err) {
        if (attempt >= maxRetries) { throw err; }
        attempt += 1;
        if (onRetry) { onRetry(attempt); }
        var delay = Math.min(8000, 700 * Math.pow(2, attempt));
        return new Promise(function (resolve) { setTimeout(resolve, delay); }).then(run);
      });
    }
    return run();
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  global.BvmApi = { post: post, postWithRetry: postWithRetry, escapeHtml: escapeHtml, base: apiBase };
})(window);
