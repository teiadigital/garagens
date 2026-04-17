(function ($, Drupal) {
  "use strict";

  /**
   * Remove entity reference ID from "entity_autocomplete" field.
   *
   * @type {{attach: Drupal.behaviors.autocompleteReferenceEntityId.attach}}
   */
  Drupal.behaviors.autocompleteReferenceEntityId = {
    attach: function (context) {
      // Remove reference IDs for autocomplete elements on init.
      $(".form-autocomplete", context).each(function () {
        let splitValues =
          this.value && this.value !== "false"
            ? Drupal.autocomplete.splitValues(this.value)
            : [];

        if (splitValues.length > 0) {
          let labelValues = [];
          for (let i in splitValues) {
            let value = splitValues[i].trim();
            let entityIdMatch = value.match(/\s*\((.*?)\)$/);
            if (entityIdMatch) {
              labelValues[i] = value.replace(entityIdMatch[0], "");
            }
          }

          if (labelValues.length > 0) {
            $(this).data("real-value", splitValues.join(", "));
            this.value = labelValues.join(", ");
          }
        }
      });
    },
  };

  if (Drupal.autocomplete) {
    let autocomplete = Drupal.autocomplete.options;
    autocomplete.originalValues = [];
    autocomplete.labelValues = [];

    /**
     * Add custom select handler.
     */
    autocomplete.select = function (event, ui) {
      autocomplete.labelValues = Drupal.autocomplete.splitValues(
        event.target.value
      );
      autocomplete.labelValues.pop();
      autocomplete.labelValues.push(ui.item.label);
      autocomplete.originalValues.push(ui.item.value);

      $(event.target).data(
        "real-value",
        autocomplete.originalValues.join(", ")
      );
      event.target.value = autocomplete.labelValues.join(", ");

      return false;
    };
  }

  let bool = false;

  $(".field--name-field-p-sidebar-menu .field__item").each(function (index) {
    let href = $(this).find(".paragraph--type--sidebar-menu").attr("href");

    if (href != "") {
      if (window.location.pathname.indexOf(href) >= 0) {
        if (index != 0) {
          $(this).addClass("active");
          bool = true;
        }
      }
    }
  });

  $(".field--name-field-p-sidebar-menu .field__item").each(function (index) {
    if (bool == false) {
      if (index == 0) {
        $(this).addClass("active");
      }
    }
  });

  $(".paragraph--type--why-choose-us .content-text").each(function (index) {
    var color = $(this).data("color");

    $(this).find(".field--name-field-pre-title").css("border-color", color);

    $(this).find(".field--name-field-link a").css("border-color", color);
    $(this).find(".field--name-field-link a").css("background-color", color);

    $(this)
      .find(".field--name-field-link a")
      .hover(
        function () {
          $(this).css("color", color);
          $(this).css("background-color", "transparent");
        },
        function () {
          $(this).css("color", "white");
          $(this).css("background-color", color);
        }
      );
  });

  $(document).ready(function () {
    if (!$.fn.owlCarousel) return;
    $(".owl-carousel-homepage").owlCarousel({
      center: true,
      items: 1,
      loop: true,
      margin: 10,
      autoplay: true,
      autoplayTimeout: 10000,
      autoplayHoverPause: true,
      smartSpeed: 1000,
      dots: true,
    });

    $(".owl-carousel-advertising").owlCarousel({
      center: true,
      items: 1,
      loop: true,
      margin: 10,
      autoplay: true,
      autoplayTimeout: 4000,
      autoplayHoverPause: true,
      smartSpeed: 1000,
      dots: true,
    });
  });


})(jQuery, Drupal);
