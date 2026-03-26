(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.garagemDisponibilidade = {
    attach: function (context, settings) {

      const pickerInput = context.querySelector('.garagem-disponibilidade-picker');
      if (!pickerInput) return;

      const disponibilidadeUrl = drupalSettings.garagemDisponibilidade
        ? drupalSettings.garagemDisponibilidade.disponibilidadeUrl
        : null;

      let datasOcupadas = [];

      const iniciar = () => {
        const datasDesativadas = datasOcupadas.map(reserva => {
          if (reserva.indefinido) {
            return { from: new Date(parseInt(reserva.inicio) * 1000), to: new Date(9999, 0, 1) };
          }
          return {
            from: new Date(parseInt(reserva.inicio) * 1000),
            to: new Date(parseInt(reserva.fim) * 1000),
          };
        });

        flatpickr(pickerInput, {
          mode: 'range',
          dateFormat: 'd/m/Y',
          minDate: 'today',
          locale: {
            ...flatpickr.l10ns.pt,
            rangeSeparator: ' - ',
          },
          onClose: function(selectedDates) {
            // Se selecionou só uma data, duplicar para dia único.
            if (selectedDates.length === 1) {
              this.setDate([selectedDates[0], selectedDates[0]], false);
            }
          },
          onDayCreate: function(dObj, dStr, fp, dayElem) {
            const date = new Date(dayElem.dateObj);
            date.setHours(0, 0, 0, 0);
            const isOcupado = datasDesativadas.some(range => {
              const from = new Date(range.from);
              from.setHours(0, 0, 0, 0);
              if (range.to) {
                const to = new Date(range.to);
                to.setHours(23, 59, 59, 999);
                return date >= from && date <= to;
              }
              return date >= from;
            });
            if (isOcupado) dayElem.classList.add('reservado');
          },
        });
      };

      if (disponibilidadeUrl) {
        fetch(disponibilidadeUrl)
          .then(r => r.json())
          .then(data => {
            datasOcupadas = data.datas || data;
            iniciar();
          })
          .catch(() => iniciar());
      } else {
        iniciar();
      }
    }
  };

})(Drupal, drupalSettings);
