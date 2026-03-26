(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.garagemReservaFlatpickr = {
    attach: function (context, settings) {

      const rangeInput = context.querySelector('.garagem-flatpickr-range');
      const indefinidoCheckbox = context.querySelector('#edit-indefinido');
      const precoInfo = context.querySelector('#preco-calculado');

      if (!rangeInput) return;

      const disponibilidadeUrl = drupalSettings.garagemReservas.disponibilidadeUrl;
      let datasOcupadas = [];

      fetch(disponibilidadeUrl)
        .then(response => response.json())
        .then(data => {
          datasOcupadas = data.datas || data;
          iniciarFlatpickr();
        })
        .catch(() => {
          iniciarFlatpickr();
        });

      let picker = null;

      function iniciarFlatpickr() {
        const datasDesativadas = datasOcupadas.map(reserva => {
          if (reserva.indefinido) {
            return { from: new Date(parseInt(reserva.inicio) * 1000), to: new Date(9999, 0, 1) };
          }
          return {
            from: new Date(parseInt(reserva.inicio) * 1000),
            to: new Date(parseInt(reserva.fim) * 1000),
          };
        });

        picker = flatpickr(rangeInput, {
          mode: 'range',
          dateFormat: 'd/m/Y',
          minDate: 'today',
          disable: datasDesativadas,
          locale: {
            ...flatpickr.l10ns.pt,
            rangeSeparator: ' - ',
          },
          onDayCreate: function(dObj, dStr, fp, dayElem) {
            const date = new Date(dayElem.dateObj);
            date.setHours(0, 0, 0, 0);
            const isDisabled = datasDesativadas.some(range => {
              const from = new Date(range.from);
              from.setHours(0, 0, 0, 0);
              if (range.to) {
                const to = new Date(range.to);
                to.setHours(23, 59, 59, 999);
                return date >= from && date <= to;
              }
              return date >= from;
            });
            if (isDisabled) dayElem.classList.add('reservado');
          },
          onChange: function(selectedDates) {
            if (selectedDates.length === 2) {
              calcularPreco(selectedDates[0], selectedDates[1]);
            } else {
              if (precoInfo) precoInfo.innerHTML = '';
            }
          },
        });
      }

      if (indefinidoCheckbox) {
        indefinidoCheckbox.addEventListener('change', function() {
          if (this.checked) {
            if (picker) {
              picker.set('mode', 'single');
              picker.clear();
            }
            if (precoInfo) {
              precoInfo.innerHTML = '<div class="preco-info-box"><div class="preco-nota">' +
                Drupal.t('O valor a pagar corresponde ao primeiro mês. Os pagamentos seguintes são acordados diretamente com o proprietário.') +
                '</div></div>';
            }
          } else {
            if (picker) {
              picker.set('mode', 'range');
              picker.clear();
            }
            if (precoInfo) precoInfo.innerHTML = '';
          }
        });
      }

      function calcularPreco(inicio, fim) {
        if (!precoInfo) return;
        const diffMs = fim - inicio;
        const diffDias = diffMs / (1000 * 60 * 60 * 24);
        const diffMeses = diffDias / 30;

        let duracao = diffMeses >= 1
          ? Math.round(diffMeses) + ' ' + Drupal.t('mês(es)')
          : Math.ceil(diffDias) + ' ' + Drupal.t('dia(s)');

        precoInfo.innerHTML = '<div class="preco-info-box">' +
          '<div class="preco-duracao">' + Drupal.t('Duração') + ': <strong>' + duracao + '</strong></div>' +
          '<div class="preco-nota">' + Drupal.t('O preço exato será confirmado após aprovação.') + '</div>' +
          '</div>';
      }
    }
  };

})(Drupal, drupalSettings);
