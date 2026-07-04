(function (Drupal, drupalSettings) {
  "use strict";

  function escHtml(str) {
    if (!str) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function formatIsoDate(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
  }

  function parseIsoDate(value) {
    if (!value) return null;
    return new Date(`${value}T00:00:00`);
  }

  function isSameCalendarDay(a, b) {
    return a.getFullYear() === b.getFullYear()
      && a.getMonth() === b.getMonth()
      && a.getDate() === b.getDate();
  }

  function isDefaultRadius(value) {
    return value === "" || Number(value) === 15;
  }

  function bindAutocomplete(form) {
    const locationInput = form.querySelector("#pesquisa-location");
    const latInput = form.querySelector("#pesquisa-lat");
    const lngInput = form.querySelector("#pesquisa-lng");
    const bboxNInput = form.querySelector("#pesquisa-bbox-n");
    const bboxSInput = form.querySelector("#pesquisa-bbox-s");
    const bboxEInput = form.querySelector("#pesquisa-bbox-e");
    const bboxWInput = form.querySelector("#pesquisa-bbox-w");
    const autocomplete = form.querySelector("#pesquisa-autocomplete");
    const limparBtn = form.querySelector("#limpar-location");

    let acTimeout = null;

    function clearViewportBounds() {
      if (bboxNInput) bboxNInput.value = "";
      if (bboxSInput) bboxSInput.value = "";
      if (bboxEInput) bboxEInput.value = "";
      if (bboxWInput) bboxWInput.value = "";
    }

    function fecharAutocomplete() {
      autocomplete.classList.add("hidden");
      autocomplete.innerHTML = "";
    }

    function showClearButton() {
      limparBtn.classList.remove("hidden");
      limparBtn.style.display = "flex";
    }

    function hideClearButton() {
      limparBtn.classList.add("hidden");
      limparBtn.style.display = "";
    }

    function renderAutocomplete(features) {
      const vistos = new Set();
      const unicos = features.filter((feature) => {
        const props = feature.properties || {};
        if ((props.countrycode || "").toLowerCase() !== "pt") return false;
        if (!props.name || !["place", "boundary"].includes(props.osm_key)) return false;
        if (vistos.has(props.name)) return false;
        vistos.add(props.name);
        return true;
      });

      if (!unicos.length) {
        fecharAutocomplete();
        return;
      }

      autocomplete.innerHTML = unicos.slice(0, 5).map((feature) => {
        const props = feature.properties || {};
        const coords = feature.geometry.coordinates;
        const detalhe = [props.city || props.county, props.state, props.country]
          .filter(Boolean)
          .filter((value, index, values) => values.indexOf(value) === index)
          .join(", ");

        return `<div class="autocomplete-item" data-lat="${coords[1]}" data-lng="${coords[0]}" data-label="${escHtml(props.name)}">
          <svg class="w-4 h-4 text-gray-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
          </svg>
          <div class="flex flex-col min-w-0">
            <span class="text-sm font-semibold text-gray-900 truncate">${escHtml(props.name)}</span>
            <span class="text-xs text-gray-400 truncate">${escHtml(detalhe)}</span>
          </div>
        </div>`;
      }).join("");

      autocomplete.classList.remove("hidden");

      autocomplete.querySelectorAll(".autocomplete-item").forEach((item) => {
        item.addEventListener("click", () => {
          locationInput.value = item.dataset.label || "";
          latInput.value = item.dataset.lat || "";
          lngInput.value = item.dataset.lng || "";
          clearViewportBounds();
          showClearButton();
          fecharAutocomplete();
        });
      });
    }

    locationInput.addEventListener("input", function () {
      clearTimeout(acTimeout);
      latInput.value = "";
      lngInput.value = "";
      clearViewportBounds();

      const query = this.value.trim();
      if (!query) {
        hideClearButton();
        fecharAutocomplete();
        return;
      }

      showClearButton();

      if (query.length < 2) {
        fecharAutocomplete();
        return;
      }

      acTimeout = setTimeout(() => {
        fetch(`https://photon.komoot.io/api/?q=${encodeURIComponent(query)}&limit=200&lang=en&osm_tag=place&osm_tag=boundary`)
          .then((response) => response.json())
          .then((data) => renderAutocomplete(data.features || []))
          .catch(() => fecharAutocomplete());
      }, 300);
    });

    limparBtn.addEventListener("click", (event) => {
      event.stopPropagation();
      locationInput.value = "";
      latInput.value = "";
      lngInput.value = "";
      clearViewportBounds();
      hideClearButton();
      fecharAutocomplete();
    });

    document.addEventListener("click", (event) => {
      if (!autocomplete.contains(event.target) && event.target !== locationInput) {
        fecharAutocomplete();
      }
    });

    if (locationInput.value.trim()) {
      showClearButton();
    }

    return {
      clearViewportBounds,
      closeAutocomplete: fecharAutocomplete,
      showClearButton,
      hideClearButton,
    };
  }

  function bindDatePicker(form, tiposDisponiveis) {
    const campoDatas = form.querySelector("#campo-datas");
    const datasPanel = form.querySelector("#datas-panel");
    const datasDisplay = form.querySelector("#datas-display");
    const tipoInput = form.querySelector("#pesquisa-tipo");
    const inputInicio = form.querySelector("#pesquisa-data-inicio");
    const inputFim = form.querySelector("#pesquisa-data-fim");
    const tipoTabsEl = form.querySelector("#tipo-tabs");

    let dataInicioVal = inputInicio.value || "";
    let dataFimVal = inputFim.value || "";
    let panelAberto = false;
    let fp = null;

    function syncDateDisplay() {
      if (!dataInicioVal) {
        datasDisplay.textContent = Drupal.t("Adicionar datas");
        return;
      }

      const start = flatpickr.formatDate(new Date(`${dataInicioVal}T00:00:00`), "d/m/Y");
      if (tipoInput.value === "dia" && dataFimVal) {
        const end = flatpickr.formatDate(new Date(`${dataFimVal}T00:00:00`), "d/m/Y");
        datasDisplay.textContent = `${start} – ${end}`;
      }
      else {
        datasDisplay.textContent = start;
      }
    }

    function fecharPanel() {
      datasPanel.classList.add("datas-panel-hidden");
      datasPanel.classList.remove("datas-panel-visible");
      campoDatas.classList.remove("campo-ativo");
      panelAberto = false;
    }

    function criarFp(modo) {
      if (fp) fp.destroy();
      const calEl = form.querySelector("#datas-cal");
      calEl.innerHTML = "";
      const defaultDates = modo === "dia"
        ? [parseIsoDate(dataInicioVal), parseIsoDate(dataFimVal)].filter(Boolean)
        : parseIsoDate(dataInicioVal);

      fp = flatpickr(calEl, {
        locale: "pt",
        inline: true,
        mode: modo === "dia" ? "range" : "single",
        minDate: "today",
        showMonths: window.innerWidth >= 640 ? 2 : 1,
        dateFormat: "d/m/Y",
        disableMobile: true,
        defaultDate: defaultDates,
        onReady(_dates, _str, instance) {
          const cal = instance.calendarContainer;
          if (!cal) return;
          cal.style.position = "static";
          cal.style.boxShadow = "none";
          cal.style.border = "none";
          cal.style.margin = "0 auto";
          if (defaultDates) {
            instance.jumpToDate(Array.isArray(defaultDates) ? defaultDates[0] : defaultDates);
          }
        },
        onChange(selectedDates) {
          if (!selectedDates[0]) return;

          dataInicioVal = formatIsoDate(selectedDates[0]);
          inputInicio.value = dataInicioVal;

          if (modo === "dia") {
            if (!selectedDates[1]) {
              dataFimVal = "";
              inputFim.value = "";
              return;
            }
            if (isSameCalendarDay(selectedDates[0], selectedDates[1])) {
              dataFimVal = "";
              inputFim.value = "";
              fp.clear(false);
              fp.setDate([selectedDates[0]], false);
              return;
            }
            dataFimVal = formatIsoDate(selectedDates[1]);
            inputFim.value = dataFimVal;
          }
          else {
            dataFimVal = "";
            inputFim.value = "";
          }

          syncDateDisplay();
          fecharPanel();
        },
      });
    }

    const tipoLabels = {
      dia: Drupal.t("Por dia"),
      mes: Drupal.t("Por mês"),
      ano: Drupal.t("Por ano"),
    };

    tipoTabsEl.innerHTML = "";
    tiposDisponiveis.forEach((valor) => {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "tipo-tab";
      btn.dataset.valor = valor;
      btn.textContent = tipoLabels[valor] || valor;
      if (valor === tipoInput.value) {
        btn.classList.add("tipo-tab-ativo");
      }
      tipoTabsEl.appendChild(btn);
    });

    if (!tiposDisponiveis.includes(tipoInput.value) && tiposDisponiveis.length) {
      tipoInput.value = tiposDisponiveis[0];
      tipoTabsEl.querySelector(".tipo-tab")?.classList.add("tipo-tab-ativo");
    }

    campoDatas.addEventListener("click", (event) => {
      event.stopPropagation();
      if (panelAberto) {
        fecharPanel();
        return;
      }

      datasPanel.classList.remove("datas-panel-hidden");
      datasPanel.classList.add("datas-panel-visible");
      campoDatas.classList.add("campo-ativo");
      panelAberto = true;
      criarFp(tipoInput.value);
    });

    document.addEventListener("click", (event) => {
      if (panelAberto && !datasPanel.contains(event.target) && !campoDatas.contains(event.target)) {
        fecharPanel();
      }
    });

    tipoTabsEl.addEventListener("click", (event) => {
      const tab = event.target.closest(".tipo-tab");
      if (!tab) return;

      event.stopPropagation();
      tipoInput.value = tab.dataset.valor || "dia";
      tipoTabsEl.querySelectorAll(".tipo-tab").forEach((item) => item.classList.remove("tipo-tab-ativo"));
      tab.classList.add("tipo-tab-ativo");

      dataInicioVal = "";
      dataFimVal = "";
      inputInicio.value = "";
      inputFim.value = "";
      syncDateDisplay();
      criarFp(tipoInput.value);
    });

    syncDateDisplay();

    return {
      fecharPanel,
      getTipo: () => tipoInput.value,
      getDateStart: () => dataInicioVal,
      getDateEnd: () => dataFimVal,
    };
  }

  Drupal.behaviors.garagemPesquisa = {
    attach: function (context) {
      const form = context.querySelector("#pesquisa-form");
      if (!form || form.dataset.garagemPesquisaAttached === "true") return;
      form.dataset.garagemPesquisaAttached = "true";

      const cfg = drupalSettings.garagemPesquisa || {};
      const initialState = cfg.initialState || {};
      const ajaxUrl = cfg.ajaxUrl || "/pesquisa/ajax";
      const pageLimit = Number(cfg.pageLimit || 12);
      const tiposDisponiveis = cfg.tipos || [];
      const modoBloco = form.dataset.mode === "block";
      const pesquisaUrl = form.dataset.pesquisaUrl || "/garagens";

      const locationInput = form.querySelector("#pesquisa-location");
      const latInput = form.querySelector("#pesquisa-lat");
      const lngInput = form.querySelector("#pesquisa-lng");
      const radiusInput = form.querySelector("#pesquisa-radius");
      const bboxNInput = form.querySelector("#pesquisa-bbox-n");
      const bboxSInput = form.querySelector("#pesquisa-bbox-s");
      const bboxEInput = form.querySelector("#pesquisa-bbox-e");
      const bboxWInput = form.querySelector("#pesquisa-bbox-w");

      const autocomplete = bindAutocomplete(form);
      const datas = bindDatePicker(form, tiposDisponiveis);

      form.querySelector("#campo-onde")?.addEventListener("click", () => {
        locationInput.focus();
      });

      if (modoBloco) {
        const mapaEl = document.getElementById("pesquisa-map");
        const mapaStatus = document.getElementById("homepage-mapa-status");
        const mapaStatusText = document.getElementById("homepage-mapa-status-text");
        const mapaLocalizacaoBtn = document.getElementById("homepage-mapa-localizacao");
        const mapaPopup = document.getElementById("mapa-popup");
        const mapaPopupFoto = document.getElementById("mapa-popup-foto");
        const mapaPopupTitulo = document.getElementById("mapa-popup-titulo");
        const mapaPopupLocalidade = document.getElementById("mapa-popup-localidade");
        const mapaPopupPreco = document.getElementById("mapa-popup-preco");
        const mapaPopupLink = document.getElementById("mapa-popup-link");
        const mapaPopupFechar = document.getElementById("mapa-popup-fechar");
        let homepageMap = null;
        let homepageMarkers = {};
        let homepageMapMoveTimeout = null;
        let homepageRequestId = 0;
        let homepageTemLocalizacao = false;

        function setHomepageStatus(message, showLocationButton = !homepageTemLocalizacao) {
          if (!mapaStatus) return;
          if (mapaStatusText) {
            mapaStatusText.textContent = message;
          }
          else {
            mapaStatus.textContent = message;
          }
          if (mapaLocalizacaoBtn) {
            mapaLocalizacaoBtn.classList.toggle("hidden", !showLocationButton);
          }
          mapaStatus.classList.toggle("hidden", !message);
        }

        function initHomepageMap() {
          if (!mapaEl || homepageMap) return;

          homepageMap = L.map(mapaEl, { scrollWheelZoom: true, zoomControl: true }).setView([39.5, -8.0], 7);
          L.tileLayer("https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png", {
            attribution: "© OpenStreetMap contributors © CARTO",
            subdomains: "abcd",
            maxZoom: 19,
          }).addTo(homepageMap);

          homepageMap.on("movestart zoomstart", () => {
            mapaPopup?.classList.add("hidden");
            setHomepageStatus(Drupal.t("A carregar pontos..."));
          });

          homepageMap.on("moveend zoomend", () => {
            clearTimeout(homepageMapMoveTimeout);
            homepageMapMoveTimeout = setTimeout(carregarHomepageViewport, 500);
          });

          setTimeout(() => homepageMap?.invalidateSize(), 200);
        }

        function updateHomepageViewportInputs() {
          if (!homepageMap || !bboxNInput || !bboxSInput || !bboxEInput || !bboxWInput) return;
          const bounds = homepageMap.getBounds();
          bboxNInput.value = String(bounds.getNorth());
          bboxSInput.value = String(bounds.getSouth());
          bboxEInput.value = String(bounds.getEast());
          bboxWInput.value = String(bounds.getWest());
        }

        function limparHomepageMapa() {
          if (!homepageMap) return;
          Object.values(homepageMarkers).forEach((marker) => homepageMap.removeLayer(marker));
          homepageMarkers = {};
        }

        function buildHomepageGaragemUrl(baseUrl) {
          const params = new URLSearchParams();
          params.set("tipo", datas.getTipo());
          if (datas.getDateStart()) params.set("data_inicio", datas.getDateStart());
          if (datas.getTipo() === "dia" && datas.getDateEnd()) params.set("data_fim", datas.getDateEnd());

          const query = params.toString();
          return query ? `${baseUrl}${baseUrl.includes("?") ? "&" : "?"}${query}` : baseUrl;
        }

        function mostrarHomepagePopup(item, marker) {
          if (!homepageMap || !mapaPopup) return;

          const tipo = datas.getTipo();
          const preco =
            (tipo === "dia" && item.preco_dia) ? item.preco_dia
              : (tipo === "mes" && item.preco_mes) ? item.preco_mes
                : (tipo === "ano" && item.preco_ano) ? item.preco_ano
                  : item.preco_dia || item.preco_mes || item.preco_ano || null;
          const unidade = tipo === "dia" ? Drupal.t("dia") : tipo === "mes" ? Drupal.t("mês") : Drupal.t("ano");

          mapaPopupFoto.src = item.foto;
          mapaPopupFoto.alt = item.title;
          mapaPopupTitulo.textContent = item.title;
          mapaPopupLocalidade.textContent = item.locality;
          mapaPopupPreco.textContent = preco
            ? `${preco.toLocaleString("pt-PT", { minimumFractionDigits: 2, maximumFractionDigits: 2 })} € / ${unidade}`
            : "";
          mapaPopupLink.href = buildHomepageGaragemUrl(item.url);

          const point = homepageMap.latLngToContainerPoint(marker.getLatLng());
          const popupWidth = 260;
          const popupHeight = 240;
          let left = point.x - popupWidth / 2;
          let top = point.y - popupHeight - 16;

          if (left < 8) left = 8;
          if (left + popupWidth > mapaEl.offsetWidth - 8) {
            left = mapaEl.offsetWidth - popupWidth - 8;
          }
          if (top < 8) top = point.y + 24;

          mapaPopup.style.left = `${left}px`;
          mapaPopup.style.top = `${top}px`;
          mapaPopup.classList.remove("hidden");
        }

        function criarHomepageMarker(item) {
          if (!homepageMap || !item.lat || !item.lng) return;

          const tipo = datas.getTipo() || "dia";
          const precoValor =
            (tipo === "dia" && item.preco_dia) ? item.preco_dia
              : (tipo === "mes" && item.preco_mes) ? item.preco_mes
                : (tipo === "ano" && item.preco_ano) ? item.preco_ano
                  : item.preco_dia || item.preco_mes || item.preco_ano || null;

          const icon = L.divIcon({
            className: "",
            html: `<div class="pesquisa-marker" data-nid="${item.nid}">${precoValor ? `€${Math.round(precoValor)}` : ""}</div>`,
            iconSize: null,
            iconAnchor: [0, 0],
          });

          const marker = L.marker([item.lat, item.lng], { icon })
            .addTo(homepageMap)
            .on("click", () => mostrarHomepagePopup(item, marker));

          homepageMarkers[item.nid] = marker;
        }

        function carregarHomepageViewport(clearStatus = true) {
          if (!homepageMap) return;
          updateHomepageViewportInputs();
          const requestId = ++homepageRequestId;

          const bounds = homepageMap.getBounds();
          const params = {
            bbox_n: bounds.getNorth(),
            bbox_s: bounds.getSouth(),
            bbox_e: bounds.getEast(),
            bbox_w: bounds.getWest(),
            tipo: datas.getTipo(),
            map_only: 1,
          };

          if (datas.getDateStart()) params.date_start = datas.getDateStart();
          if (datas.getDateEnd()) params.date_end = datas.getDateEnd();

          fetch(`${ajaxUrl}?${new URLSearchParams(params).toString()}`)
            .then((response) => response.json())
            .then((data) => {
              if (requestId !== homepageRequestId) return;
              limparHomepageMapa();
              (data.markers || []).forEach((item) => criarHomepageMarker(item));
              if (clearStatus) {
                setHomepageStatus("", false);
              }
            })
            .catch(() => {
              if (requestId === homepageRequestId) {
                setHomepageStatus(Drupal.t("Não foi possível carregar o mapa."));
              }
            });
        }

        function pedirLocalizacaoHomepage() {
          if (!mapaEl || !("geolocation" in navigator)) {
            setHomepageStatus("", false);
            carregarHomepageViewport();
            return;
          }

          setHomepageStatus(Drupal.t("A pedir a sua localização..."), false);
          navigator.geolocation.getCurrentPosition(
            (position) => {
              const lat = position.coords.latitude;
              const lng = position.coords.longitude;
              homepageTemLocalizacao = true;
              latInput.value = String(lat);
              lngInput.value = String(lng);
              locationInput.value = Drupal.t("A sua localização");
              autocomplete.showClearButton();
              homepageMap.setView([lat, lng], 11);
              carregarHomepageViewport();
            },
            (error) => {
              const denied = error && error.code === error.PERMISSION_DENIED;
              setHomepageStatus(
                denied
                  ? Drupal.t("A localização está bloqueada. Autorize no browser e tente novamente.")
                  : Drupal.t("Não foi possível obter a sua localização. Tente novamente."),
                true,
              );
              carregarHomepageViewport(false);
            },
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 },
          );
        }

        if (mapaEl && typeof L !== "undefined") {
          initHomepageMap();
          setHomepageStatus(Drupal.t("Use a sua localização para ver garagens perto de si."), true);
          pedirLocalizacaoHomepage();
        }

        mapaLocalizacaoBtn?.addEventListener("click", (event) => {
          event.preventDefault();
          pedirLocalizacaoHomepage();
        });

        mapaPopupFechar?.addEventListener("click", (event) => {
          event.preventDefault();
          event.stopPropagation();
          mapaPopup.classList.add("hidden");
        });

        form.addEventListener("submit", (event) => {
          event.preventDefault();
          datas.fecharPanel();

          const params = new URLSearchParams();
          if (locationInput.value.trim() && locationInput.value.trim() !== Drupal.t("A sua localização")) {
            params.set("q", locationInput.value.trim());
          }
          if (latInput.value) params.set("lat", latInput.value);
          if (lngInput.value) params.set("lng", lngInput.value);
          if (bboxNInput?.value) params.set("bbox_n", bboxNInput.value);
          if (bboxSInput?.value) params.set("bbox_s", bboxSInput.value);
          if (bboxEInput?.value) params.set("bbox_e", bboxEInput.value);
          if (bboxWInput?.value) params.set("bbox_w", bboxWInput.value);
          if (radiusInput?.value && !isDefaultRadius(radiusInput.value)) {
            params.set("radius", radiusInput.value);
          }
          params.set("tipo", datas.getTipo());
          if (datas.getDateStart()) params.set("date_start", datas.getDateStart());
          if (datas.getDateEnd()) params.set("date_end", datas.getDateEnd());

          window.location.href = `${pesquisaUrl}${params.toString() ? `?${params.toString()}` : ""}`;
        });

        return;
      }

      const resultados = document.getElementById("pesquisa-results");
      const empty = document.getElementById("pesquisa-empty");
      const header = document.getElementById("pesquisa-header");
      const count = document.getElementById("pesquisa-count");
      const loadMoreEl = document.getElementById("pesquisa-load-more");
      const sentinel = document.getElementById("pesquisa-scroll-sentinel");
      const mapaWrapper = document.getElementById("pesquisa-mapa-wrapper");
      const mapaPopup = document.getElementById("mapa-popup");
      const mapaPopupFoto = document.getElementById("mapa-popup-foto");
      const mapaPopupTitulo = document.getElementById("mapa-popup-titulo");
      const mapaPopupLocalidade = document.getElementById("mapa-popup-localidade");
      const mapaPopupPreco = document.getElementById("mapa-popup-preco");
      const mapaPopupLink = document.getElementById("mapa-popup-link");
      const mapaPopupFechar = document.getElementById("mapa-popup-fechar");

      let offsetAtual = Number(initialState.offset || document.querySelectorAll(".garagem-card").length);
      let totalResultados = Number(initialState.total || document.querySelectorAll(".garagem-card").length);
      let aCarregarMais = false;
      let map = null;
      let markers = {};
      let mapProntoParaViewport = false;
      let mapListenersAttached = false;
      let mapMoveTimeout = null;
      let userViewportInteraction = false;
      let currentParams = { ...(initialState.params || {}) };

      function bindCardsHover() {
        document.querySelectorAll(".garagem-card").forEach((card) => {
          if (card.dataset.hoverBound === "true") return;
          card.dataset.hoverBound = "true";
          const nid = card.dataset.nid;
          card.addEventListener("mouseenter", () => activarMarker(nid));
          card.addEventListener("mouseleave", () => desativarMarker(nid));
        });
      }

      function updateHeader(total) {
        if (!total) {
          header.classList.add("hidden");
          empty.classList.remove("hidden");
          return;
        }

        empty.classList.add("hidden");
        header.classList.remove("hidden");
        header.classList.add("flex");
        count.textContent = `${total} garagem${total !== 1 ? "s" : ""} encontrada${total !== 1 ? "s" : ""}`;
      }

      function buildGaragemUrl(baseUrl) {
        if (!datas.getDateStart()) return baseUrl;

        const params = new URLSearchParams();
        params.set("tipo", datas.getTipo());
        params.set("data_inicio", datas.getDateStart());
        if (datas.getTipo() === "dia" && datas.getDateEnd()) {
          params.set("data_fim", datas.getDateEnd());
        }

        return `${baseUrl}${baseUrl.includes("?") ? "&" : "?"}${params.toString()}`;
      }

      function renderCard(item) {
        const tipo = datas.getTipo() || "dia";
        const comDatas = Boolean(datas.getDateStart());
        let preco = null;
        let tipoReal = null;

        if (comDatas) {
          if (tipo === "dia" && item.preco_dia) { preco = item.preco_dia; tipoReal = "dia"; }
          else if (tipo === "mes" && item.preco_mes) { preco = item.preco_mes; tipoReal = "mes"; }
          else if (tipo === "ano" && item.preco_ano) { preco = item.preco_ano; tipoReal = "ano"; }
        }
        else {
          if (tipo === "dia" && item.preco_dia) { preco = item.preco_dia; tipoReal = "dia"; }
          else if (tipo === "mes" && item.preco_mes) { preco = item.preco_mes; tipoReal = "mes"; }
          else if (tipo === "ano" && item.preco_ano) { preco = item.preco_ano; tipoReal = "ano"; }
          else if (item.preco_dia) { preco = item.preco_dia; tipoReal = "dia"; }
          else if (item.preco_mes) { preco = item.preco_mes; tipoReal = "mes"; }
          else if (item.preco_ano) { preco = item.preco_ano; tipoReal = "ano"; }
        }

        const unidade = tipoReal === "dia" ? Drupal.t("dia") : tipoReal === "mes" ? Drupal.t("mês") : Drupal.t("ano");
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

      function limparMapa() {
        if (!map) return;
        Object.values(markers).forEach((marker) => map.removeLayer(marker));
        markers = {};
      }

      function activarMarker(nid) {
        document.querySelector(`.pesquisa-marker[data-nid="${nid}"]`)?.classList.add("ativo");
      }

      function desativarMarker(nid) {
        document.querySelector(`.pesquisa-marker[data-nid="${nid}"]`)?.classList.remove("ativo");
      }

      function initMapa() {
        if (map) return;
        map = L.map("pesquisa-map", { zoomControl: true }).setView([39.5, -8.0], 7);
        L.tileLayer("https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png", {
          attribution: "© OpenStreetMap contributors © CARTO",
          subdomains: "abcd",
          maxZoom: 19,
        }).addTo(map);
      }

      function updateViewportInputs() {
        if (!map || !bboxNInput || !bboxSInput || !bboxEInput || !bboxWInput) return;
        const bounds = map.getBounds();
        bboxNInput.value = String(bounds.getNorth());
        bboxSInput.value = String(bounds.getSouth());
        bboxEInput.value = String(bounds.getEast());
        bboxWInput.value = String(bounds.getWest());
      }

      function getBboxParams() {
        if (!map) return {};
        const bounds = map.getBounds();
        const params = {
          bbox_n: bounds.getNorth(),
          bbox_s: bounds.getSouth(),
          bbox_e: bounds.getEast(),
          bbox_w: bounds.getWest(),
          tipo: datas.getTipo(),
        };

        if (radiusInput?.value && !isDefaultRadius(radiusInput.value)) {
          params.radius = radiusInput.value;
        }
        if (datas.getDateStart()) params.date_start = datas.getDateStart();
        if (datas.getDateEnd()) params.date_end = datas.getDateEnd();

        return params;
      }

      function mostrarPopupMapa(item, marker) {
        const tipo = datas.getTipo();
        let preco = null;
        if (datas.getDateStart()) {
          if (tipo === "dia" && item.preco_dia) preco = item.preco_dia;
          else if (tipo === "mes" && item.preco_mes) preco = item.preco_mes;
          else if (tipo === "ano" && item.preco_ano) preco = item.preco_ano;
        }

        const unidade = tipo === "dia" ? "dia" : tipo === "mes" ? "mês" : "ano";

        mapaPopupFoto.src = item.foto;
        mapaPopupFoto.alt = item.title;
        mapaPopupTitulo.textContent = item.title;
        mapaPopupLocalidade.textContent = item.locality;
        mapaPopupPreco.textContent = preco
          ? `${preco.toLocaleString("pt-PT", { minimumFractionDigits: 2 })} € / ${unidade}`
          : "";
        mapaPopupLink.href = buildGaragemUrl(item.url);

        const point = map.latLngToContainerPoint(marker.getLatLng());
        const mapEl = document.getElementById("pesquisa-map");
        const popupWidth = 260;
        const popupHeight = 240;
        let left = point.x - popupWidth / 2;
        let top = point.y - popupHeight - 16;

        if (left < 8) left = 8;
        if (left + popupWidth > mapEl.offsetWidth - 8) {
          left = mapEl.offsetWidth - popupWidth - 8;
        }
        if (top < 8) top = point.y + 24;

        mapaPopup.style.left = `${left}px`;
        mapaPopup.style.top = `${top}px`;
        mapaPopup.classList.remove("hidden");
      }

      function criarMarker(item) {
        if (!map) return;

        const tipo = datas.getTipo() || "dia";
        const precoValor =
          (tipo === "dia" && item.preco_dia) ? item.preco_dia
            : (tipo === "mes" && item.preco_mes) ? item.preco_mes
              : (tipo === "ano" && item.preco_ano) ? item.preco_ano
                : item.preco_dia || item.preco_mes || item.preco_ano || null;

        const icon = L.divIcon({
          className: "",
          html: `<div class="pesquisa-marker" data-nid="${item.nid}">${precoValor ? `€${Math.round(precoValor)}` : ""}</div>`,
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

      function mostrarMapa() {
        if (mapaWrapper.classList.contains("hidden")) {
          mapaWrapper.classList.remove("hidden");
        }

        if (window.innerWidth >= 1024) {
          resultados.classList.add("com-mapa");
          resultados.classList.remove("lg:grid-cols-3", "xl:grid-cols-4");
          resultados.classList.add("grid-cols-2");
        }

        initMapa();
        setTimeout(() => map?.invalidateSize(), 200);

        if (mapListenersAttached) return;

        map.on("mousedown touchstart", () => {
          userViewportInteraction = true;
        });

        map.on("wheel", () => {
          userViewportInteraction = true;
        });

        map.on("dragstart zoomstart", () => {
          if (!mapProntoParaViewport || !userViewportInteraction) return;
          latInput.value = "";
          lngInput.value = "";
          autocomplete.clearViewportBounds();
        });

        map.on("moveend zoomend", () => {
          if (!mapProntoParaViewport || !userViewportInteraction) return;
          clearTimeout(mapMoveTimeout);
          mapMoveTimeout = setTimeout(() => {
            carregarViewport();
            userViewportInteraction = false;
          }, 700);
        });

        mapListenersAttached = true;
      }

      function carregarViewport() {
        const bboxParams = getBboxParams();
        if (!Object.keys(bboxParams).length) return;

        currentParams = { ...bboxParams };
        updateViewportInputs();
        resultados.classList.add("a-carregar");

        fetch(`${ajaxUrl}?${new URLSearchParams({ ...bboxParams, map_only: 1 }).toString()}`)
          .then((response) => response.json())
          .then((data) => {
            limparMapa();
            (data.markers || []).forEach((item) => criarMarker(item));
          })
          .catch(() => {});

        fetch(`${ajaxUrl}?${new URLSearchParams({ ...bboxParams, limit: pageLimit, offset: 0 }).toString()}`)
          .then((response) => response.json())
          .then((data) => {
            const items = data.results || [];
            totalResultados = Number(data.total || 0);
            offsetAtual = items.length;
            resultados.innerHTML = items.map((item) => renderCard(item)).join("");
            updateHeader(totalResultados);
            bindCardsHover();
          })
          .catch(() => {
            resultados.innerHTML = "";
            updateHeader(0);
          })
          .finally(() => {
            resultados.classList.remove("a-carregar");
          });
      }

      function carregarMais() {
        if (aCarregarMais || offsetAtual >= totalResultados) return;

        aCarregarMais = true;
        loadMoreEl.style.display = "flex";

        fetch(`${ajaxUrl}?${new URLSearchParams({ ...currentParams, limit: pageLimit, offset: offsetAtual }).toString()}`)
          .then((response) => response.json())
          .then((data) => {
            const items = data.results || [];
            offsetAtual += items.length;
            items.forEach((item) => {
              resultados.insertAdjacentHTML("beforeend", renderCard(item));
            });
            bindCardsHover();
          })
          .catch(() => {})
          .finally(() => {
            aCarregarMais = false;
            loadMoreEl.style.display = "";
          });
      }

      const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) {
          carregarMais();
        }
      }, { rootMargin: "300px" });

      if (sentinel) observer.observe(sentinel);

      form.addEventListener("submit", () => {
        datas.fecharPanel();

        if (!latInput.value && !lngInput.value) {
          autocomplete.clearViewportBounds();
        }
      });

      mapaPopupFechar?.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        mapaPopup.classList.add("hidden");
      });

      updateHeader(totalResultados);
      bindCardsHover();

      if (initialState.showMap) {
        mostrarMapa();

        if (initialState.lat && initialState.lng) {
          map.setView([parseFloat(initialState.lat), parseFloat(initialState.lng)], 11);
        }

        limparMapa();
        (initialState.markers || []).forEach((item) => criarMarker(item));
        mapProntoParaViewport = true;
        if (!initialState.lat && !initialState.lng) {
          updateViewportInputs();
        }
      }
    },
  };
})(Drupal, drupalSettings);
