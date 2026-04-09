(function (Drupal, drupalSettings) {
  "use strict";

  Drupal.behaviors.garagemPesquisa = {
    attach: function (context) {
      const form = context.querySelector("#pesquisa-form");
      if (!form) return;

      const cfg = drupalSettings.garagemPesquisa || {};
      const ajaxUrl = cfg.ajaxUrl || "/pesquisa/ajax";

      const locationInput = document.getElementById("pesquisa-location");
      const latInput = document.getElementById("pesquisa-lat");
      const lngInput = document.getElementById("pesquisa-lng");
      const autocomplete = document.getElementById("pesquisa-autocomplete");
      const resultados = document.getElementById("pesquisa-results");
      const loading = document.getElementById("pesquisa-loading");
      const empty = document.getElementById("pesquisa-empty");
      const count = document.getElementById("pesquisa-count");
      const inicial = document.getElementById("pesquisa-inicial");
      const header = document.getElementById("pesquisa-header");
      const mapaWrapper = document.getElementById("pesquisa-mapa-wrapper");

      // --- Mapa ---
      let map = null;
      let markers = {};
      let pesquisaUtilizador = false;

      function initMapa() {
        if (map) return;
        map = L.map("pesquisa-map", { zoomControl: true }).setView([39.5, -8.0], 7);
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
          attribution: "© OpenStreetMap contributors",
          maxZoom: 18,
        }).addTo(map);
      }

      let mapMoveTimeout = null;
      let mapProntoParaViewport = false;

      function mostrarMapa() {
        if (mapaWrapper.classList.contains("hidden")) {
          mapaWrapper.classList.remove("hidden");
          if (window.innerWidth >= 1024) {
            resultados.classList.add("com-mapa");
            resultados.classList.remove("lg:grid-cols-3", "xl:grid-cols-4");
            resultados.classList.add("grid-cols-2");
          }
          initMapa();
          setTimeout(() => map && map.invalidateSize(), 200);

          // Listener para carregar markers conforme o viewport
          map.on("dragstart zoomstart", () => {
            if (!mapProntoParaViewport) return;
            locationInput.value = Drupal.t("Garagens na área do mapa");
            latInput.value = "";
            lngInput.value = "";
            limparBtn.classList.add("hidden");
            limparBtn.style.display = "";
          });
          map.on("moveend zoomend", () => {
            if (!mapProntoParaViewport) return;
            clearTimeout(mapMoveTimeout);
            mapMoveTimeout = setTimeout(carregarViewport, 1200);
          });
        }
      }

      function getBboxParams() {
        if (!map) return {};
        const b = map.getBounds();
        const p = {
          bbox_n: b.getNorth(),
          bbox_s: b.getSouth(),
          bbox_e: b.getEast(),
          bbox_w: b.getWest(),
          tipo: tipoInput.value,
        };
        if (dataInicioVal) p.date_start = dataInicioVal;
        if (dataFimVal)    p.date_end   = dataFimVal;
        return p;
      }

      function carregarMarkersIniciais() {
        if (!map) return;
        const params = new URLSearchParams({ ...getBboxParams(), map_only: 1 });
        fetch(`${ajaxUrl}?${params}`)
          .then((r) => r.json())
          .then((data) => {
            limparMapa();
            (data.markers || []).forEach((item) => criarMarker(item));
          })
          .catch(() => {});
      }

      function carregarViewport() {
        if (!map) return;
        const bboxParams = getBboxParams();

        // Markers
        fetch(`${ajaxUrl}?${new URLSearchParams({ ...bboxParams, map_only: 1 })}`)
          .then((r) => r.json())
          .then((data) => {
            limparMapa();
            (data.markers || []).forEach((item) => criarMarker(item));
          })
          .catch(() => {});

        // Lista
        offsetAtual = 0;
        totalResultados = 0;
        aCarregarMais = false;
        parametrosAtual = bboxParams;
        empty.classList.add("hidden");
        resultados.classList.add("a-carregar");

        fetch(`${ajaxUrl}?${new URLSearchParams({ ...bboxParams, limit: LIMIT, offset: 0 })}`)
          .then((r) => r.json())
          .then((data) => {
            resultados.classList.remove("a-carregar");
            const items = data.results || [];
            const total = data.total || 0;
            resultados.innerHTML = "";
            header.classList.add("hidden");
            if (!items.length) {
              empty.classList.remove("hidden");
              return;
            }
            totalResultados = total;
            offsetAtual = items.length;
            count.textContent = `${total} garagem${total !== 1 ? "s" : ""} encontrada${total !== 1 ? "s" : ""}`;
            header.classList.remove("hidden");
            header.classList.add("flex");
            resultados.innerHTML = items.map((item) => renderCard(item)).join("");
            document.querySelectorAll(".garagem-card").forEach((card) => {
              const nid = card.dataset.nid;
              card.addEventListener("mouseenter", () => activarMarker(nid));
              card.addEventListener("mouseleave", () => desativarMarker(nid));
            });
          })
          .catch(() => { resultados.classList.remove("a-carregar"); });
      }

      function limparMapa() {
        if (!map) return;
        Object.values(markers).forEach((m) => map.removeLayer(m));
        markers = {};
      }

      const mapaPopup = document.getElementById("mapa-popup");
      const mapaPopupFoto = document.getElementById("mapa-popup-foto");
      const mapaPopupTitulo = document.getElementById("mapa-popup-titulo");
      const mapaPopupLocal = document.getElementById("mapa-popup-localidade");
      const mapaPopupPreco = document.getElementById("mapa-popup-preco");
      const mapaPopupLink = document.getElementById("mapa-popup-link");
      const mapaPopupFechar = document.getElementById("mapa-popup-fechar");

      mapaPopupFechar &&
        mapaPopupFechar.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          mapaPopup.classList.add("hidden");
        });

      function mostrarPopupMapa(item, marker) {
        const tipo = tipoInput.value;
        let preco = null;
        if (dataInicioVal) {
          if (tipo === "dia" && item.preco_dia) preco = item.preco_dia;
          else if (tipo === "mes" && item.preco_mes) preco = item.preco_mes;
          else if (tipo === "ano" && item.preco_ano) preco = item.preco_ano;
        }
        const unidade = tipo === "dia" ? "dia" : tipo === "mes" ? "mês" : "ano";

        mapaPopupFoto.src = item.foto;
        mapaPopupFoto.alt = item.title;
        mapaPopupTitulo.textContent = item.title;
        mapaPopupLocal.textContent = item.locality;
        mapaPopupPreco.textContent = preco
          ? `${preco.toLocaleString("pt-PT", { minimumFractionDigits: 2 })} € / ${unidade}`
          : "";
        mapaPopupLink.href = buildGaragemUrl(item.url);

        // Posicionar acima do marker
        const point = map.latLngToContainerPoint(marker.getLatLng());
        const mapEl = document.getElementById("pesquisa-map");
        const popupW = 260,
          popupH = 240;
        let left = point.x - popupW / 2;
        let top = point.y - popupH - 16;
        if (left < 8) left = 8;
        if (left + popupW > mapEl.offsetWidth - 8)
          left = mapEl.offsetWidth - popupW - 8;
        if (top < 8) top = point.y + 24;

        mapaPopup.style.left = left + "px";
        mapaPopup.style.top = top + "px";
        mapaPopup.classList.remove("hidden");
      }

      function criarMarker(item) {
        if (!map) return;
        const tipo = tipoInput.value;
        const precoValor =
          (tipo === "dia" && item.preco_dia) ? item.preco_dia
          : (tipo === "mes" && item.preco_mes) ? item.preco_mes
          : (tipo === "ano" && item.preco_ano) ? item.preco_ano
          : item.preco_dia || item.preco_mes || item.preco_ano || null;
        const preco = precoValor ? `€${Math.round(precoValor)}` : "";
        const icon = L.divIcon({
          className: "",
          html: `<div class="pesquisa-marker" data-nid="${item.nid}">${preco}</div>`,
          iconSize: null,
          iconAnchor: [0, 0],
        });
        const marker = L.marker([item.lat, item.lng], { icon })
          .addTo(map)
          .on("click", () => {
            activarMarker(item.nid);
            mostrarPopupMapa(item, marker);
          })
          .on("mouseover", () => activarMarker(item.nid))
          .on("mouseout", () => desativarMarker(item.nid));
        markers[item.nid] = marker;
      }

      function activarMarker(nid) {
        document
          .querySelector(`.pesquisa-marker[data-nid="${nid}"]`)
          ?.classList.add("ativo");
      }
      function desativarMarker(nid) {
        document
          .querySelector(`.pesquisa-marker[data-nid="${nid}"]`)
          ?.classList.remove("ativo");
      }
      function activarCard(nid) {
        document
          .querySelectorAll(".garagem-card")
          .forEach((c) => c.classList.remove("ativo"));
        document
          .querySelector(`.garagem-card[data-nid="${nid}"]`)
          ?.classList.add("ativo");
      }

      // --- Paginação / Infinite scroll ---
      const LIMIT = 12;
      let offsetAtual = 0;
      let totalResultados = 0;
      let aCarregarMais = false;
      let parametrosAtual = {};
      const loadMoreEl = document.getElementById("pesquisa-load-more");
      const sentinel   = document.getElementById("pesquisa-scroll-sentinel");

      const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && !aCarregarMais && offsetAtual < totalResultados) {
          carregarMais();
        }
      }, { rootMargin: "200px" });

      observer.observe(sentinel);

      function carregarMais() {
        aCarregarMais = true;
        loadMoreEl.style.display = "flex";
        const params = new URLSearchParams({ ...parametrosAtual, limit: LIMIT, offset: offsetAtual });
        fetch(`${ajaxUrl}?${params}`)
          .then((r) => r.json())
          .then((data) => {
            aCarregarMais = false;
            loadMoreEl.style.display = "";
            const items = data.results || [];
            offsetAtual += items.length;
            items.forEach((item) => {
              resultados.insertAdjacentHTML("beforeend", renderCard(item));
            });
            document.querySelectorAll(".garagem-card").forEach((card) => {
              const nid = card.dataset.nid;
              card.addEventListener("mouseenter", () => activarMarker(nid));
              card.addEventListener("mouseleave", () => desativarMarker(nid));
            });
          })
          .catch(() => { aCarregarMais = false; loadMoreEl.style.display = ""; });
      }

      // --- Painel Datas ---
      const campoDatas = document.getElementById("campo-datas");
      const datasPanel = document.getElementById("datas-panel");
      const datasDisplay = document.getElementById("datas-display");
      const tipoInput = document.getElementById("pesquisa-tipo");
      const inputInicio = document.getElementById("pesquisa-data-inicio");
      const inputFim = document.getElementById("pesquisa-data-fim");

      let dataInicioVal = "";
      let dataFimVal = "";
      let panelAberto = false;

      function abrirPanel() {
        datasPanel.classList.remove("datas-panel-hidden");
        datasPanel.classList.add("datas-panel-visible");
        panelAberto = true;
        campoDatas.classList.add("campo-ativo");
        if (!fp) criarFp(tipoInput.value);
      }

      function fecharPanel() {
        datasPanel.classList.add("datas-panel-hidden");
        datasPanel.classList.remove("datas-panel-visible");
        panelAberto = false;
        campoDatas.classList.remove("campo-ativo");
      }

      campoDatas.addEventListener("click", (e) => {
        e.stopPropagation();
        panelAberto ? fecharPanel() : abrirPanel();
      });

      document.addEventListener("click", (e) => {
        if (
          panelAberto &&
          !datasPanel.contains(e.target) &&
          !campoDatas.contains(e.target)
        ) {
          fecharPanel();
        }
      });

      // Flatpickr inline — criado/recriado conforme o tipo
      let fp = null;

      function criarFp(modo) {
        if (fp) fp.destroy();
        const calEl = document.getElementById("datas-cal");
        calEl.innerHTML = "";
        // Reset width constraint based on mode
        calEl.style.maxWidth = "";
        fp = flatpickr(calEl, {
          locale: "pt",
          inline: true,
          mode: modo === "dia" ? "range" : "single",
          minDate: "today",
          showMonths: window.innerWidth >= 640 ? 2 : 1,
          dateFormat: "d/m/Y",
          disableMobile: true,
          onReady: function (_dates, _str, instance) {
            const cal = instance.calendarContainer;
            if (!cal) return;
            cal.style.position = "static";
            cal.style.boxShadow = "none";
            cal.style.border = "none";
            cal.style.margin = "0 auto";
            // Garantir que os meses ficam com position relative para as setas
            const months = cal.querySelector(".flatpickr-months");
            if (months) months.style.position = "relative";
          },
          onChange: function (selectedDates) {
            const tipo = tipoInput.value;
            if (!selectedDates[0]) return;

            const fmt = (d) =>
              `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
            dataInicioVal = fmt(selectedDates[0]);

            if (tipo === "dia") {
              if (!selectedDates[1]) {
                dataFimVal = "";
                return;
              }
              dataFimVal = fmt(selectedDates[1]);
              const d0 = selectedDates[0],
                d1 = selectedDates[1];
              datasDisplay.textContent = `${flatpickr.formatDate(d0, "d/m/Y")} – ${flatpickr.formatDate(d1, "d/m/Y")}`;
              mostrarLimparDatas();
              inputInicio.value = dataInicioVal;
              inputFim.value = dataFimVal;
              fecharPanel();
            } else {
              datasDisplay.textContent = flatpickr.formatDate(
                selectedDates[0],
                "d/m/Y",
              );
              mostrarLimparDatas();
              inputInicio.value = dataInicioVal;
              dataFimVal = "";
              inputFim.value = "";
              fecharPanel();
            }
            // Se estiver em modo bbox, atualizar com a área atual do mapa
            if (!latInput.value && mapProntoParaViewport) {
              carregarViewport();
            }
          },
        });
      }

      // --- Botão limpar datas ---
      const limparDatasBtn = document.getElementById("limpar-datas");

      function mostrarLimparDatas() {
        limparDatasBtn.classList.remove("hidden");
        limparDatasBtn.style.display = "flex";
      }
      function esconderLimparDatas() {
        limparDatasBtn.classList.add("hidden");
        limparDatasBtn.style.display = "";
      }

      limparDatasBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        dataInicioVal = "";
        dataFimVal = "";
        inputInicio.value = "";
        inputFim.value = "";
        datasDisplay.textContent = Drupal.t("Adicionar datas");
        if (fp) fp.clear();
        esconderLimparDatas();
        fecharPanel();
        pesquisar();
      });

      // fp é inicializado na primeira abertura do painel

      // Gerar tabs com base nos tipos disponíveis
      const tiposDisponiveis = cfg.tipos || [];
      const tipoLabels = {
        dia: Drupal.t("Por dia"),
        mes: Drupal.t("Por mês"),
        ano: Drupal.t("Por ano"),
      };
      const tipoTabsEl = document.getElementById("tipo-tabs");

      tiposDisponiveis.forEach((valor, idx) => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "tipo-tab" + (idx === 0 ? " tipo-tab-ativo" : "");
        btn.dataset.valor = valor;
        btn.textContent = tipoLabels[valor] || valor;
        tipoTabsEl.appendChild(btn);
      });

      // Definir tipo padrão como o primeiro disponível
      if (tiposDisponiveis.length > 0) {
        tipoInput.value = tiposDisponiveis[0];
      }

      // Tabs tipo dentro do painel
      tipoTabsEl.addEventListener("click", (e) => {
        const tab = e.target.closest(".tipo-tab");
        if (!tab) return;
        e.stopPropagation();
        const valor = tab.dataset.valor;
        tipoInput.value = valor;
        tipoTabsEl
          .querySelectorAll(".tipo-tab")
          .forEach((t) => t.classList.remove("tipo-tab-ativo"));
        tab.classList.add("tipo-tab-ativo");
        dataInicioVal = "";
        dataFimVal = "";
        inputInicio.value = "";
        inputFim.value = "";
        datasDisplay.textContent = Drupal.t("Adicionar datas");
        criarFp(valor);
      });

      // --- Botão limpar localização ---
      const limparBtn = document.getElementById("limpar-location");

      document.getElementById("campo-onde").addEventListener("click", () => {
        locationInput.focus();
      });

      locationInput.addEventListener("focus", function () {
        if (!latInput.value && this.value === Drupal.t("Garagens na área do mapa")) {
          this.value = "";
          limparBtn.classList.add("hidden");
          limparBtn.style.display = "";
        }
      });

      locationInput.addEventListener("input", function () {
        if (this.value.trim()) {
          limparBtn.classList.remove("hidden");
          limparBtn.style.display = "flex";
        } else {
          limparBtn.classList.add("hidden");
          limparBtn.style.display = "";
        }
      });

      limparBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        locationInput.value = "";
        latInput.value = "";
        lngInput.value = "";
        limparBtn.classList.add("hidden");
        limparBtn.style.display = "";
        fecharAutocomplete();
        pesquisar();
      });

      // --- Autocomplete Photon ---
      let acTimeout;
      locationInput.addEventListener("input", function () {
        clearTimeout(acTimeout);
        const q = this.value.trim();
        if (q.length === 0) {
          fecharAutocomplete();
          latInput.value = "";
          lngInput.value = "";
          return;
        }
        if (q.length < 2) {
          fecharAutocomplete();
          return;
        }
        acTimeout = setTimeout(() => {
          fetch(
            `https://photon.komoot.io/api/?q=${encodeURIComponent(q)}&limit=200&lang=en&osm_tag=place&osm_tag=boundary`,
          )
            .then((r) => r.json())
            .then((data) => renderAutocomplete(data.features || []))
            .catch(() => fecharAutocomplete());
        }, 300);
      });

      function renderAutocomplete(features) {
        if (!features.length) {
          fecharAutocomplete();
          return;
        }
        const tiposValidos = ["place", "boundary"];
        const vistos = new Set();
        const unicos = features.filter((f) => {
          const p = f.properties || {};
          if ((p.countrycode || "").toLowerCase() !== "pt") return false;
          if (!p.name) return false;
          if (!tiposValidos.includes(p.osm_key)) return false;
          if (vistos.has(p.name)) return false;
          vistos.add(p.name);
          return true;
        });
        if (!unicos.length) {
          fecharAutocomplete();
          return;
        }
        autocomplete.innerHTML = unicos
          .slice(0, 5)
          .map((f) => {
            const p = f.properties || {};
            const coords = f.geometry.coordinates; // [lng, lat]
            const detalhe = [p.city || p.county, p.state, p.country]
              .filter(Boolean)
              .filter((v, i, a) => a.indexOf(v) === i)
              .join(", ");
            return `<div class="autocomplete-item" data-lat="${coords[1]}" data-lng="${coords[0]}" data-label="${escHtml(p.name)}">
            <svg class="w-4 h-4 text-gray-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
            </svg>
            <div class="flex flex-col min-w-0">
              <span class="text-sm font-semibold text-gray-900 truncate">${escHtml(p.name)}</span>
              <span class="text-xs text-gray-400 truncate">${escHtml(detalhe)}</span>
            </div>
          </div>`;
          })
          .join("");
        autocomplete.classList.remove("hidden");
        autocomplete.querySelectorAll(".autocomplete-item").forEach((el) => {
          el.addEventListener("click", () => {
            locationInput.value = el.dataset.label;
            latInput.value = el.dataset.lat;
            lngInput.value = el.dataset.lng;
            fecharAutocomplete();
          });
        });
      }

      function fecharAutocomplete() {
        autocomplete.classList.add("hidden");
        autocomplete.innerHTML = "";
      }

      document.addEventListener("click", (e) => {
        if (!autocomplete.contains(e.target) && e.target !== locationInput)
          fecharAutocomplete();
      });

      // --- Pesquisa ---
      form.addEventListener("submit", (e) => {
        e.preventDefault();
        fecharPanel();
        pesquisaUtilizador = true;
        pesquisar();
      });

      pesquisar(); // arranque sem mostrar mapa

      function pesquisar() {
        const lat = latInput.value;
        const lng = lngInput.value;
        const tipo = tipoInput.value;
        const inicio = dataInicioVal;
        const fim = dataFimVal;

        parametrosAtual = { radius: 15, tipo };
        if (lat && lng) { parametrosAtual.lat = lat; parametrosAtual.lng = lng; }
        if (inicio) parametrosAtual.date_start = inicio;
        if (fim)    parametrosAtual.date_end   = fim;

        offsetAtual = 0;
        totalResultados = 0;
        aCarregarMais = false;

        inicial.classList.add("hidden");
        empty.classList.add("hidden");
        header.classList.add("hidden");
        resultados.innerHTML = "";
        loading.classList.remove("hidden");
        limparMapa();

        const params = new URLSearchParams({ ...parametrosAtual, limit: LIMIT, offset: 0 });
        fetch(`${ajaxUrl}?${params}`)
          .then((r) => r.json())
          .then((data) => renderResultados(data.results || [], data.total || 0))
          .catch(() => {
            loading.classList.add("hidden");
            empty.classList.remove("hidden");
          });
      }

      function renderResultados(items, total) {
        loading.classList.add("hidden");

        if (pesquisaUtilizador) mostrarMapa();

        const searchLat = parametrosAtual.lat;
        const searchLng = parametrosAtual.lng;
        mapProntoParaViewport = false;
        setTimeout(() => {
          if (!map) return;
          if (searchLat && searchLng) {
            map.setView([parseFloat(searchLat), parseFloat(searchLng)], 11);
          }
          setTimeout(() => {
            carregarMarkersIniciais();
            mapProntoParaViewport = true;
          }, 500);
        }, 250);

        if (!items.length) {
          empty.classList.remove("hidden");
          return;
        }

        totalResultados = total;
        offsetAtual = items.length;

        count.textContent = `${total} garagem${total !== 1 ? "s" : ""} encontrada${total !== 1 ? "s" : ""}`;
        header.classList.remove("hidden");
        header.classList.add("flex");

        resultados.innerHTML = items.map((item) => renderCard(item)).join("");

        document.querySelectorAll(".garagem-card").forEach((card) => {
          const nid = card.dataset.nid;
          card.addEventListener("mouseenter", () => activarMarker(nid));
          card.addEventListener("mouseleave", () => desativarMarker(nid));
        });
      }

      function renderCard(item) {
        const tipo = tipoInput.value;
        let preco = null;
        if (dataInicioVal) {
          if (tipo === "dia" && item.preco_dia) preco = item.preco_dia;
          else if (tipo === "mes" && item.preco_mes) preco = item.preco_mes;
          else if (tipo === "ano" && item.preco_ano) preco = item.preco_ano;
        }
        const unidade =
          tipo === "dia"
            ? Drupal.t("dia")
            : tipo === "mes"
              ? Drupal.t("mês")
              : Drupal.t("ano");
        const precoHtml = preco
          ? `<p class="card-preco"><strong>${preco.toLocaleString("pt-PT", { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €</strong> / ${unidade}</p>`
          : "";
        return `
          <a href="${escHtml(buildGaragemUrl(item.url))}" class="garagem-card" data-nid="${item.nid}">
            <div class="garagem-card-img">
              <img src="${escHtml(item.foto)}" alt="${escHtml(item.title)}" loading="lazy">
            </div>
            <div class="card-info">
              <div class="card-info-top">
                <p class="card-titulo">${escHtml(item.title)}</p>
                ${precoHtml}
              </div>
              <p class="card-localidade">${escHtml(item.locality)}</p>
            </div>
          </a>`;
      }

      function buildGaragemUrl(baseUrl) {
        if (!dataInicioVal) return baseUrl;
        const params = new URLSearchParams();
        params.set("tipo", tipoInput.value);
        params.set("data_inicio", dataInicioVal);
        if (tipoInput.value === "dia" && dataFimVal) params.set("data_fim", dataFimVal);
        return baseUrl + (baseUrl.includes("?") ? "&" : "?") + params.toString();
      }

      function escHtml(str) {
        if (!str) return "";
        return String(str)
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;");
      }
    },
  };
})(Drupal, drupalSettings);
