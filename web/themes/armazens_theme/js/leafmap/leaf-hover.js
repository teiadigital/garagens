(function ($, Drupal) {
  Drupal.behaviors.leafletHoverPopup = {
    attach: function (context, settings) {
      const mapId = 'leaflet-map-view-mapa-armazens-block-1';
      const mapElement = document.getElementById(mapId);

      if (!mapElement || !mapElement._leaflet_map) {
        setTimeout(() => Drupal.behaviors.leafletHoverPopup.attach(context, settings), 300);
        return;
      }

      const map = mapElement._leaflet_map;

      if (!map || Object.keys(map._layers).length <= 1) {
        setTimeout(() => Drupal.behaviors.leafletHoverPopup.attach(context, settings), 300);
        return;
      }

      map.eachLayer(function (layer) {
        if (layer instanceof L.Marker && typeof layer.getTooltip === 'function') {
          const tooltip = layer.getTooltip();

          if (!tooltip) return;

          const content = tooltip.getContent();

          // Substitui tooltip por popup
          layer.bindPopup(content);
          layer.unbindTooltip();

          // Hover mostra popup
          layer.on('mouseover', function () {
            this.openPopup();
          });

          // Sai do hover, fecha popup (ou comenta esta linha se quiser deixar aberto)
          layer.on('mouseout', function () {
            this.closePopup();
          });
        }
      });
    }
  };
})(jQuery, Drupal);

