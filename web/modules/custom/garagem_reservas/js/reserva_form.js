(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.garagemReservaFlatpickr = {
    attach: function (context, settings) {

      const datasInput = context.querySelector('.garagem-flatpickr-datas');
      const tipoPrecoBotoes = context.querySelectorAll('.tipo-preco-btn');
      const tipoPrecHidden = context.querySelector('#tipo-preco-value');
      const infoPeriodo = context.querySelector('#garagem-info-periodo');
      const precoInfo = context.querySelector('#preco-calculado');
      const renovacaoWrapper = context.querySelector('#renovacao-wrapper');
      const renovacaoCheckbox = context.querySelector('#edit-renovacao-automatica');

      if (!datasInput) return;

      const cfg = drupalSettings.garagemReservas || {};
      const disponibilidadeUrl = cfg.disponibilidadeUrl;
      const tiposAtivos = cfg.tiposAtivos || {};
      const aceitaRenovacao = cfg.aceitaRenovacao || false;

      let datasOcupadas = [];
      let picker = null;
      let tipoAtual = tipoPrecHidden ? tipoPrecHidden.value : 'dia';

      fetch(disponibilidadeUrl)
        .then(r => r.json())
        .then(data => {
          datasOcupadas = data.datas || data;
          iniciarPicker();
          atualizarModo(tipoAtual);
        })
        .catch(() => {
          iniciarPicker();
          atualizarModo(tipoAtual);
        });

      function getDatasDesativadas() {
        return datasOcupadas.map(reserva => {
          if (reserva.renovacao_automatica) {
            return { from: new Date(parseInt(reserva.inicio) * 1000), to: new Date(9999, 0, 1) };
          }
          // O dia de fim é exclusivo — subtrair 1ms para não bloquear esse dia.
          const fimMs = parseInt(reserva.fim) * 1000 - 1;
          return {
            from: new Date(parseInt(reserva.inicio) * 1000),
            to: new Date(fimMs),
          };
        });
      }

      function iniciarPicker() {
        const desativadas = getDatasDesativadas();

        picker = flatpickr(datasInput, {
          mode: tipoAtual === 'dia' ? 'range' : 'single',
          dateFormat: 'd/m/Y',
          minDate: 'today',
          disable: desativadas,
          locale: {
            ...flatpickr.l10ns.pt,
            rangeSeparator: ' - ',
          },
          onDayCreate: function(dObj, dStr, fp, dayElem) {
            const date = new Date(dayElem.dateObj);
            date.setHours(0, 0, 0, 0);
            const ocupado = desativadas.some(range => {
              const from = new Date(range.from); from.setHours(0,0,0,0);
              if (range.to) {
                const to = new Date(range.to); to.setHours(23,59,59,999);
                return date >= from && date <= to;
              }
              return date >= from;
            });
            if (ocupado) dayElem.classList.add('reservado');
          },
          onChange: function(selectedDates) {
            if (tipoAtual === 'dia') {
              if (selectedDates.length === 2) {
                const dias = Math.ceil((selectedDates[1] - selectedDates[0]) / (1000 * 60 * 60 * 24));
                const preco = (tiposAtivos['dia'] || 0) * dias;
                mostrarInfo(dias + ' ' + Drupal.t('dia(s)'), preco);
              } else {
                limparInfo();
              }
            } else if (selectedDates.length === 1) {
              const inicio = selectedDates[0];
              const fim = calcularFim(inicio, tipoAtual);

              // Verificar se o período calculado colide com datas ocupadas.
              const desativadas = getDatasDesativadas();
              const inicioMs = inicio.getTime();
              const fimMs = fim.getTime();
              const colide = desativadas.some(range => {
                const fromMs = range.from.getTime();
                const toMs = range.to ? range.to.getTime() : Infinity;
                return fromMs < fimMs && toMs > inicioMs;
              });

              if (colide) {
                if (infoPeriodo) {
                  infoPeriodo.innerHTML = '<span style="color:#dc2626;font-size:13px;">'
                    + Drupal.t('Este período inclui datas já reservadas. Escolha outra data de início.')
                    + '</span>';
                }
                if (precoInfo) precoInfo.innerHTML = '';
                this.clear();
                esconderRenovacao();
                return;
              }

              const label = tipoAtual === 'mes'
                ? Drupal.t('1 mês — até @fim', {'@fim': formatDate(fim)})
                : Drupal.t('1 ano — até @fim', {'@fim': formatDate(fim)});
              mostrarInfo(label, tiposAtivos[tipoAtual] || 0);
              verificarRenovacao(inicio);
            }
          },
        });
      }

      function atualizarModo(tipo) {
        tipoAtual = tipo;
        if (picker) {
          picker.set('mode', tipo === 'dia' ? 'range' : 'single');
          picker.clear();
        }
        datasInput.placeholder = tipo === 'dia'
          ? Drupal.t('Selecione as datas de início e fim')
          : Drupal.t('Selecione a data de início');
        limparInfo();
        esconderRenovacao();
      }

      function calcularFim(inicio, tipo) {
        const fim = new Date(inicio);
        if (tipo === 'ano') {
          fim.setFullYear(fim.getFullYear() + 1);
        } else {
          const diaInicio = inicio.getDate();
          fim.setMonth(fim.getMonth() + 1);
          // Se houve overflow (ex: 31 maio → 1 julho), recuar para último dia do mês correto.
          if (fim.getDate() !== diaInicio) {
            fim.setDate(0); // último dia do mês anterior
          }
        }
        return fim;
      }

      function mostrarInfo(duracao, preco) {
        if (infoPeriodo) {
          infoPeriodo.innerHTML = '<span class="text-sm text-gray-600">' + duracao + '</span>';
        }
        if (precoInfo) {
          precoInfo.innerHTML = '<span class="text-sm font-medium">' + parseFloat(preco).toFixed(2) + '€</span>';
        }
      }

      function limparInfo() {
        if (infoPeriodo) infoPeriodo.innerHTML = '';
        if (precoInfo) precoInfo.innerHTML = '';
      }

      function verificarRenovacao(dataInicio) {
        if (!aceitaRenovacao || tipoAtual === 'dia') {
          esconderRenovacao();
          return;
        }
        const inicioTs = dataInicio.getTime() / 1000;

        // Esconder se já existe uma reserva com renovação automática.
        const temRenovacaoExistente = datasOcupadas.some(r => r.renovacao_automatica);

        // Esconder se existem reservas normais futuras depois da data selecionada.
        const temFuturas = datasOcupadas.some(r => {
          return !r.renovacao_automatica && parseInt(r.inicio) > inicioTs;
        });

        if (temRenovacaoExistente || temFuturas) {
          esconderRenovacao();
        } else {
          mostrarRenovacao();
        }
      }

      function mostrarRenovacao() {
        if (renovacaoWrapper) renovacaoWrapper.style.display = '';
      }

      function esconderRenovacao() {
        if (renovacaoWrapper) renovacaoWrapper.style.display = 'none';
        if (renovacaoCheckbox) renovacaoCheckbox.checked = false;
      }

      function formatDate(date) {
        return String(date.getDate()).padStart(2, '0') + '/' +
          String(date.getMonth() + 1).padStart(2, '0') + '/' +
          date.getFullYear();
      }

      // Botões de tipo.
      const estiloBase = 'padding:10px 24px;border-radius:12px;cursor:pointer;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;transition:all 0.15s;border:2px solid;';
      const estiloAtivo = estiloBase + 'border-color:#111827;background:#111827;color:#fff;';
      const estiloInativo = estiloBase + 'border-color:#e5e7eb;background:transparent;color:#374151;';

      tipoPrecoBotoes.forEach(btn => {
        btn.addEventListener('click', function() {
          tipoPrecoBotoes.forEach(b => b.setAttribute('style', estiloInativo));
          this.setAttribute('style', estiloAtivo);
          if (tipoPrecHidden) tipoPrecHidden.value = this.dataset.tipo;
          atualizarModo(this.dataset.tipo);
        });
      });

      // Renovação automática.
      if (renovacaoCheckbox) {
        renovacaoCheckbox.addEventListener('change', function() {
          if (this.checked && precoInfo) {
            precoInfo.innerHTML += '<div class="text-xs text-gray-500 mt-1">' +
              Drupal.t('Renova automaticamente.') + '</div>';
          }
        });
      }
    }
  };

})(Drupal, drupalSettings);
