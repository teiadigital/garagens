(function ($, Drupal) {
  "use strict";

  /**
   * Adds an accessible show/hide control to every password field.
   */
  Drupal.behaviors.passwordVisibilityToggle = {
    attach: function (context) {
      $("input[type='password']", context).each(function () {
        const input = this;
        if (input.dataset.passwordToggleReady === "true") {
          return;
        }
        input.dataset.passwordToggleReady = "true";

        const $input = $(input);
        const $wrapper = $("<span>", { class: "password-visibility-wrapper" });
        $input.wrap($wrapper);

        const $button = $("<button>", {
          type: "button",
          class: "password-visibility-toggle",
          "aria-label": Drupal.t("Mostrar palavra-passe"),
          "aria-pressed": "false",
        }).html(
          '<svg class="password-eye password-eye--show" aria-hidden="true" viewBox="0 0 24 24"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>' +
          '<svg class="password-eye password-eye--hide" aria-hidden="true" viewBox="0 0 24 24"><path d="m3 3 18 18M10.6 6.1A9.8 9.8 0 0 1 12 6c6.5 0 10 6 10 6a17 17 0 0 1-3 3.7M6.3 6.3A16.4 16.4 0 0 0 2 12s3.5 6 10 6c1.6 0 3-.4 4.2-1M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>'
        );

        $button.on("click", function () {
          const showPassword = input.type === "password";
          input.type = showPassword ? "text" : "password";
          this.setAttribute("aria-pressed", showPassword ? "true" : "false");
          this.setAttribute(
            "aria-label",
            showPassword
              ? Drupal.t("Ocultar palavra-passe")
              : Drupal.t("Mostrar palavra-passe")
          );
          $(this).toggleClass("is-visible", showPassword);
          input.focus();
        });

        $input.after($button);
        window.requestAnimationFrame(function () {
          const wrapper = input.closest(".password-visibility-wrapper");
          if (wrapper) {
            wrapper.style.setProperty(
              "--password-input-height",
              input.getBoundingClientRect().height + "px"
            );
          }
        });
      });
    },
  };

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
    var color = "#2f5665";

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

    function applyOwlAccessibility($carousel) {
      const $dots = $carousel.find(".owl-dot");
      const total = $dots.length;

      $dots.each(function (index) {
        const isActive = $(this).hasClass("active");
        $(this).attr({
          "aria-label": Drupal.t("Ir para slide @current de @total", {
            "@current": index + 1,
            "@total": total,
          }),
          "aria-current": isActive ? "true" : "false",
        });
      });
    }

    function watchOwlAccessibility($carousel) {
      const carouselEl = $carousel.get(0);
      if (!carouselEl || typeof MutationObserver === "undefined") {
        return;
      }

      const observer = new MutationObserver(function () {
        applyOwlAccessibility($carousel);
      });

      observer.observe(carouselEl, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ["class"],
      });
    }

    function initAccessibleCarousel(selector, options) {
      $(selector)
        .on("initialized.owl.carousel refreshed.owl.carousel changed.owl.carousel", function () {
          applyOwlAccessibility($(this));
          window.setTimeout(function () {
            applyOwlAccessibility($(this));
          }.bind(this), 0);
        })
        .each(function () {
          watchOwlAccessibility($(this));
        })
        .owlCarousel(options);
    }

    initAccessibleCarousel(".owl-carousel-homepage", {
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

    initAccessibleCarousel(".owl-carousel-advertising", {
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
