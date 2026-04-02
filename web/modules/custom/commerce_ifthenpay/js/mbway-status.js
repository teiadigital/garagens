/**
 * @file
 * commerce_ifthenpay - MB WAY polling e countdown na página Concluída.
 *
 * Faz polling ao endpoint /ifthenpay/mbway/status/{order_id} a cada 3 segundos.
 * O endpoint chama a API do ifthenpay e devolve o estado do pagamento.
 *
 * Status codes ifthenpay:
 *   000 — pago
 *   020 — rejeitado pelo utilizador
 *   101 — expirou (4 minutos)
 *   122 — recusado
 */

(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.commerceIfthenpayMbway = {
    attach: function (context, settings) {

      var wrapper = document.getElementById('ifthenpay-mbway-waiting');
      if (!wrapper || wrapper.dataset.initialized) {
        return;
      }
      wrapper.dataset.initialized = 'true';

      var config = (drupalSettings && drupalSettings.commerceIfthenpayMbway) || {};

      if (!config.statusUrl || !config.token || !config.expiresAt) {
        console.warn('Commerce ifthenpay MB WAY: configuração em falta.');
        return;
      }

      var elPending   = document.getElementById('ifthenpay-mbway-state-pending');
      var elPaid      = document.getElementById('ifthenpay-mbway-state-paid');
      var elFailed    = document.getElementById('ifthenpay-mbway-state-failed');
      var elTimer     = document.getElementById('ifthenpay-mbway-timer');
      var elFailedMsg = document.getElementById('ifthenpay-mbway-failed-msg');

      // ---- Countdown ----
      var countdownInterval = setInterval(function () {
        var now       = Math.floor(Date.now() / 1000);
        var remaining = config.expiresAt - now;

        if (remaining <= 0) {
          clearInterval(countdownInterval);
          if (elTimer) elTimer.textContent = '00:00';
          return;
        }

        var minutes = Math.floor(remaining / 60);
        var seconds = remaining % 60;
        if (elTimer) {
          elTimer.textContent =
            String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        }
      }, 1000);

      // ---- Polling ----
      var pollCount    = 0;
      var MAX_POLLS    = 150;
      var pollInterval = null;

      var failMessages = {
        '020': Drupal.t('O pagamento foi rejeitado na aplicação MB WAY.'),
        '101': Drupal.t('O pedido de pagamento expirou. Por favor tente novamente.'),
        '122': Drupal.t('O pagamento foi recusado. Por favor tente novamente.'),
      };

      function poll() {
        pollCount++;

        if (pollCount > MAX_POLLS) {
          clearInterval(pollInterval);
          clearInterval(countdownInterval);
          showFailed(Drupal.t('Tempo de espera excedido. Por favor contacte o suporte.'));
          return;
        }

        var url = config.statusUrl + '?token=' + encodeURIComponent(config.token);

        fetch(url, {
          method:      'GET',
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
          .then(function (response) {
            if (!response.ok) { throw new Error('HTTP ' + response.status); }
            return response.json();
          })
          .then(function (data) {
            if (data.status === 'paid') {
              clearInterval(pollInterval);
              clearInterval(countdownInterval);
              showPaid();
            }
            else if (data.status === 'failed') {
              clearInterval(pollInterval);
              clearInterval(countdownInterval);
              var msg = failMessages[data.code] || Drupal.t('O pagamento não foi confirmado.');
              showFailed(msg);
            }
          })
          .catch(function () {
            // Erro de rede temporário — continuar polling.
          });
      }

      setTimeout(function () {
        poll();
        pollInterval = setInterval(poll, 3000);
      }, 2000);

      // ---- UI ----
      function showPaid() {
        if (elPending) elPending.style.display = 'none';
        if (elFailed)  elFailed.style.display  = 'none';
        if (elPaid)    elPaid.style.display     = 'block';
      }

      function showFailed(message) {
        if (elPending)   elPending.style.display   = 'none';
        if (elPaid)      elPaid.style.display      = 'none';
        if (elFailed)    elFailed.style.display    = 'block';
        if (elFailedMsg) elFailedMsg.textContent   = message;
      }

      window.addEventListener('beforeunload', function () {
        clearInterval(pollInterval);
        clearInterval(countdownInterval);
      });
    },
  };

})(Drupal, drupalSettings);
