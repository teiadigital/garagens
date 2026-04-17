(function (Drupal) {
  'use strict';

  // Esconder os botões antes de aparecerem.
  var style = document.createElement('style');
  style.textContent = '.entity-browser-use-selected, .entity-browser-show-selection { display: none !important; }';
  document.head.appendChild(style);

  Drupal.behaviors.entityBrowserAutoSelect = {
    attach: function (context) {
      context.querySelectorAll('.entity-browser-use-selected:not([data-eb-auto-clicked])').forEach(function (btn) {
        btn.setAttribute('data-eb-auto-clicked', '1');
        setTimeout(function () { btn.click(); }, 50);
      });
    }
  };

}(Drupal));
