(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.garagemReservaFlatpickr = {
    attach: function (context, settings) {

      const inicioInput = context.querySelector('.garagem-flatpickr-inicio');
      const fimInput = context.querySelector('.garagem-flatpickr-fim');
      const indefinidoCheckbox = context.querySelector('#edit-indefinido');
      const precoInfo = context.querySelector('#preco-calculado');

      if (!inicioInput) return;

      // Buscar datas ocupadas da garagem.
      const disponibilidadeUrl = drupalSettings.garagemReservas.disponibilidadeUrl;
      let datasOcupadas = [];

      fetch(disponibilidadeUrl)
        .then(response => response.json())
        .then(data => {
          datasOcupadas = data;
          iniciarFlatpickr();
        })
        .catch(() => {
          iniciarFlatpickr();
        });

      let pickerInicio = null;
      let pickerFim = null;

      function iniciarFlatpickr() {
        // Converter datas ocupadas para o formato do Flatpickr.
        const datasDesativadas = datasOcupadas.map(reserva => {
          if (reserva.indefinido) {
            return { from: new Date(parseInt(reserva.inicio) * 1000), to: new Date(9999, 0, 1) };
          }
          return {
            from: new Date(parseInt(reserva.inicio) * 1000),
            to: new Date(parseInt(reserva.fim) * 1000),
          };
        });

        // Flatpickr para data de início.
        pickerInicio = flatpickr(inicioInput, {
          enableTime: false,
          dateFormat: 'd/m/Y',
          minDate: 'today',
          disable: datasDesativadas,
          locale: 'pt',
          onDayCreate: function(dObj, dStr, fp, dayElem) {
            const date = dayElem.dateObj;
            const isDisabled = datasDesativadas.some(range => {
              const from = new Date(range.from);
              const to = range.to ? new Date(range.to) : null;
              from.setHours(0,0,0,0);
              date.setHours(0,0,0,0);
              if (to) {
                to.setHours(23,59,59,999);
                return date >= from && date <= to;
              }
              return date >= from;
            });
            if (isDisabled) {
              dayElem.classList.add('reservado');
            }
          },
          onClose: function (selectedDates) {
            if (selectedDates.length > 0 && pickerFim) {
              const minFim = new Date(selectedDates[0]);
              minFim.setDate(minFim.getDate() + 1);
              pickerFim.set('minDate', minFim);
            }
            calcularPreco();
          },
        });

        // Flatpickr para data de fim.
        if (fimInput) {
          pickerFim = flatpickr(fimInput, {
            enableTime: false,
            dateFormat: 'd/m/Y',
            minDate: 'today',
            disable: datasDesativadas,
            locale: 'pt',
            onDayCreate: function(dObj, dStr, fp, dayElem) {
              const date = dayElem.dateObj;
              const isDisabled = datasDesativadas.some(range => {
                const from = new Date(range.from);
                const to = range.to ? new Date(range.to) : null;
                from.setHours(0,0,0,0);
                date.setHours(0,0,0,0);
                if (to) {
                  to.setHours(23,59,59,999);
                  return date >= from && date <= to;
                }
                return date >= from;
              });
              if (isDisabled) {
                dayElem.classList.add('reservado');
              }
            },
            onClose: function () {
              calcularPreco();
            },
          });
        }
      }

      if (indefinidoCheckbox) {
        indefinidoCheckbox.addEventListener('change', function () {
          calcularPreco();
        });
      }

      function calcularPreco() {
        if (!precoInfo) return;

        const indefinido = indefinidoCheckbox && indefinidoCheckbox.checked;
        const inicioVal = pickerInicio && pickerInicio.selectedDates[0];

        if (!inicioVal) {
          precoInfo.innerHTML = '';
          return;
        }

        if (indefinido) {
          precoInfo.innerHTML = `
            <div class="preco-info-box">
              <div class="preco-tipo">${Drupal.t('Reserva por tempo indefinido')}</div>
              <div class="preco-nota">${Drupal.t('O valor a pagar corresponde ao primeiro mês. Os pagamentos seguintes são acordados diretamente com o proprietário.')}</div>
            </div>`;
          return;
        }

        const fimVal = pickerFim && pickerFim.selectedDates[0];
        if (!fimVal) {
          precoInfo.innerHTML = '';
          return;
        }

        const diffMs = fimVal - inicioVal;
        const diffDias = diffMs / (1000 * 60 * 60 * 24);
        const diffMeses = diffDias / 30;

        let duracao = '';

        // Preço por hora — desativado para já, pode ser reativado no futuro.
        // const diffHoras = diffMs / (1000 * 60 * 60);
        // if (diffHoras < 24) {
        //   duracao = Math.ceil(diffHoras) + ' ' + Drupal.t('hora(s)');
        // } else

        if (diffMeses >= 1) {
          duracao = Math.round(diffMeses) + ' ' + Drupal.t('mês(es)');
        } else {
          duracao = Math.ceil(diffDias) + ' ' + Drupal.t('dia(s)');
        }

        precoInfo.innerHTML = `
          <div class="preco-info-box">
            <div class="preco-duracao">${Drupal.t('Duração')}: <strong>${duracao}</strong></div>
            <div class="preco-nota">${Drupal.t('O preço exato será confirmado após aprovação.')}</div>
          </div>`;
      }
    }
  };

})(Drupal, drupalSettings);
