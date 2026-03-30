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

  $(document).ajaxComplete(function () {
    if (document.getElementById("map-armazens-list")) {
      var root = am5.Root.new("map-armazens-list");

      root.setThemes([am5themes_Animated.new(root)]);

      var urlStringSearch = "";

      var countryCode = $("select[name=field_localidade_country_code]").val();

      if (countryCode != "All") {
        var administrative_area = $(
          "select[name=field_localidade_administrative_area]"
        ).val();

        if (administrative_area != "All") {
          var chart = root.container.children.push(
            am5map.MapChart.new(root, {
              panX: "rotateX",
              panY: "rotateY",
              homeZoomLevel: 80,
              homeGeoPoint: {
                longitude: parseFloat(
                  document.getElementById("cc_map_lon").textContent
                ),
                latitude: parseFloat(
                  document.getElementById("cc_map_lat").textContent
                ),
              },
            })
          );
        } else {
          var chart = root.container.children.push(
            am5map.MapChart.new(root, {
              panX: "rotateX",
              panY: "rotateY",
              homeZoomLevel: 20,
              homeGeoPoint: {
                longitude: parseFloat(
                  document.getElementById("cc_map_lon").textContent
                ),
                latitude: parseFloat(
                  document.getElementById("cc_map_lat").textContent
                ),
              },
            })
          );
        }
      } else {
        var chart = root.container.children.push(
          am5map.MapChart.new(root, {
            panX: "rotateX",
            panY: "rotateY",
            homeZoomLevel: 5,
            homeGeoPoint: {
              longitude: -4.999559822762455,
              latitude: 40.46383610634605,
            },
          })
        );
      }

      var zoomControl = chart.set(
        "zoomControl",
        am5map.ZoomControl.new(root, {})
      );

      zoomControl.minusButton.setAll({
        stroke: am5.color(0xfff),
      });
      zoomControl.plusButton.setAll({
        stroke: am5.color(0xfff),
      });

      zoomControl.minusButton.get("background").setAll({
        fill: am5.color(0x6f9b3a),
        fillOpacity: 0.7,
        stroke: am5.color(0x6f9b3a),
        strokeWidth: 2,
        strokeOpacity: 0.3,
      });
      zoomControl.minusButton
        .get("background")
        .states.create("hover", {})
        .setAll({
          fill: am5.color(0x6f9b3a),
          fillOpacity: 1,
        });

      zoomControl.minusButton
        .get("background")
        .states.create("down", {})
        .setAll({
          fill: am5.color(0x6f9b3a),
          fillOpacity: 1,
        });

      zoomControl.plusButton.get("background").setAll({
        fill: am5.color(0x6f9b3a),
        fillOpacity: 0.7,
        stroke: am5.color(0x6f9b3a),
        strokeWidth: 2,
        strokeOpacity: 0.3,
      });

      zoomControl.plusButton
        .get("background")
        .states.create("hover", {})
        .setAll({
          fill: am5.color(0x6f9b3a),
          fillOpacity: 1,
        });

      zoomControl.plusButton
        .get("background")
        .states.create("down", {})
        .setAll({
          fill: am5.color(0x6f9b3a),
          fillOpacity: 1,
        });

      var polygonSeries = chart.series.push(
        am5map.MapPolygonSeries.new(root, {
          geoJSON: am5geodata_region_world_europeHigh,
        })
      );

      polygonSeries.events.on("datavalidated", function () {
        chart.goHome();
      });

      polygonSeries.mapPolygons.template.setAll({
        fill: am5.color(0xdadada),
      });

      var pointSeries = chart.series.push(
        am5map.ClusteredPointSeries.new(root, {})
      );

      pointSeries.set("clusteredBullet", function (root) {
        var container = am5.Container.new(root, {
          cursorOverStyle: "pointer",
        });

        var circle1 = container.children.push(
          am5.Circle.new(root, {
            radius: 8,
            tooltipY: 0,
            fill: am5.color(0xf33c12),
          })
        );

        var circle2 = container.children.push(
          am5.Circle.new(root, {
            radius: 12,
            fillOpacity: 0.3,
            tooltipY: 0,
            fill: am5.color(0xf33c12),
          })
        );

        var circle3 = container.children.push(
          am5.Circle.new(root, {
            radius: 16,
            fillOpacity: 0.3,
            tooltipY: 0,
            fill: am5.color(0xf33c12),
          })
        );

        var label = container.children.push(
          am5.Label.new(root, {
            centerX: am5.p50,
            centerY: am5.p50,
            fill: am5.color(0xffffff),
            populateText: true,
            fontSize: "8",
            text: "{value}",
          })
        );

        container.events.on("click", function (e) {
          pointSeries.zoomToCluster(e.target.dataItem);
        });

        return am5.Bullet.new(root, {
          sprite: container,
        });
      });

      // Create regular bullets
      pointSeries.bullets.push(function () {
        var circle = am5.Circle.new(root, {
          radius: 6,
          tooltipY: 0,
          fill: am5.color(0xf33c12),
          tooltipText: "{title}",
        });

        return am5.Bullet.new(root, {
          sprite: circle,
        });
      });

      fetch("/api/armazens/coordenadas/" + countryCode, {
        method: "GET",
        headers: {
          Accept: "application/json",
        },
      })
        .then((response) => response.json())
        .then((response) => {
          var cities = response["data"];

          for (var i = 0; i < cities.length; i++) {
            var city = cities[i];
            addCity(
              parseFloat(city.longitude),
              parseFloat(city.latitude),
              city.title
            );
          }

          function addCity(longitude, latitude, title) {
            pointSeries.data.push({
              geometry: { type: "Point", coordinates: [longitude, latitude] },
              title: title,
            });
          }
          chart.appear(1000, 100);
        });
    }
  });
})(jQuery, Drupal);

if (document.getElementById("map-location-front")) {
  var root = am5.Root.new("map-location-front");

  root.setThemes([am5themes_Animated.new(root)]);

  var chart = root.container.children.push(
    am5map.MapChart.new(root, {
      panX: "rotateX",
      panY: "translateY",
      homeZoomLevel: 5,
      homeGeoPoint: {
        longitude: -4.999559822762455,
        latitude: 40.46383610634605,
      },
    })
  );

  var zoomControl = chart.set("zoomControl", am5map.ZoomControl.new(root, {}));

  zoomControl.minusButton.setAll({
    stroke: am5.color(0xfff),
  });
  zoomControl.plusButton.setAll({
    stroke: am5.color(0xfff),
  });

  zoomControl.minusButton.get("background").setAll({
    fill: am5.color(0x6f9b3a),
    fillOpacity: 0.7,
    stroke: am5.color(0x6f9b3a),
    strokeWidth: 2,
    strokeOpacity: 0.3,
  });
  zoomControl.minusButton
    .get("background")
    .states.create("hover", {})
    .setAll({
      fill: am5.color(0x6f9b3a),
      fillOpacity: 1,
    });

  zoomControl.minusButton
    .get("background")
    .states.create("down", {})
    .setAll({
      fill: am5.color(0x6f9b3a),
      fillOpacity: 1,
    });

  zoomControl.plusButton.get("background").setAll({
    fill: am5.color(0x6f9b3a),
    fillOpacity: 0.7,
    stroke: am5.color(0x6f9b3a),
    strokeWidth: 2,
    strokeOpacity: 0.3,
  });

  zoomControl.plusButton
    .get("background")
    .states.create("hover", {})
    .setAll({
      fill: am5.color(0x6f9b3a),
      fillOpacity: 1,
    });

  zoomControl.plusButton
    .get("background")
    .states.create("down", {})
    .setAll({
      fill: am5.color(0x6f9b3a),
      fillOpacity: 1,
    });

  var polygonSeries = chart.series.push(
    am5map.MapPolygonSeries.new(root, {
      geoJSON: am5geodata_region_world_europeHigh,
    })
  );

  polygonSeries.events.on("datavalidated", function () {
    chart.goHome();
  });

  polygonSeries.mapPolygons.template.setAll({
    fill: am5.color(0xdadada),
  });

  var pointSeries = chart.series.push(
    am5map.ClusteredPointSeries.new(root, {})
  );

  pointSeries.set("clusteredBullet", function (root) {
    var container = am5.Container.new(root, {
      cursorOverStyle: "pointer",
    });

    var circle1 = container.children.push(
      am5.Circle.new(root, {
        radius: 8,
        tooltipY: 0,
        fill: am5.color(0xf33c12),
      })
    );

    var circle2 = container.children.push(
      am5.Circle.new(root, {
        radius: 12,
        fillOpacity: 0.3,
        tooltipY: 0,
        fill: am5.color(0xf33c12),
      })
    );

    var circle3 = container.children.push(
      am5.Circle.new(root, {
        radius: 16,
        fillOpacity: 0.3,
        tooltipY: 0,
        fill: am5.color(0xf33c12),
      })
    );

    var label = container.children.push(
      am5.Label.new(root, {
        centerX: am5.p50,
        centerY: am5.p50,
        fill: am5.color(0xffffff),
        populateText: true,
        fontSize: "8",
        text: "{value}",
      })
    );

    container.events.on("click", function (e) {
      pointSeries.zoomToCluster(e.target.dataItem);
    });

    return am5.Bullet.new(root, {
      sprite: container,
    });
  });

  // Create regular bullets
  pointSeries.bullets.push(function () {
    var circle = am5.Circle.new(root, {
      radius: 6,
      tooltipY: 0,
      fill: am5.color(0xf33c12),
      tooltipText: "{title}",
    });

    return am5.Bullet.new(root, {
      sprite: circle,
    });
  });

  fetch("/api/armazens/coordenadas", {
    method: "GET",
    headers: {
      Accept: "application/json",
    },
  })
    .then((response) => response.json())
    .then((response) => {
      var cities = response["data"];

      for (var i = 0; i < cities.length; i++) {
        var city = cities[i];
        addCity(
          parseFloat(city.longitude),
          parseFloat(city.latitude),
          city.title
        );
      }

      function addCity(longitude, latitude, title) {
        pointSeries.data.push({
          geometry: { type: "Point", coordinates: [longitude, latitude] },
          title: title,
        });
      }
      chart.appear(1000, 100);
    });
}

if (document.getElementById("map-armazens-list")) {
  var root = am5.Root.new("map-armazens-list");

  root.setThemes([am5themes_Animated.new(root)]);

  var urlStringSearch = "";

  var countryCode = document.getElementById(
    "edit-field-localidade-country-code"
  ).value;

  if (countryCode != "All") {
    var administrative_area = document.getElementById(
      "edit-field-localidade-administrative-area"
    ).value;

    if (administrative_area != "All") {
      var chart = root.container.children.push(
        am5map.MapChart.new(root, {
          panX: "rotateX",
          panY: "rotateY",
          homeZoomLevel: 80,
          homeGeoPoint: {
            longitude: parseFloat(
              document.getElementById("cc_map_lon").textContent
            ),
            latitude: parseFloat(
              document.getElementById("cc_map_lat").textContent
            ),
          },
        })
      );
    } else {
      var chart = root.container.children.push(
        am5map.MapChart.new(root, {
          panX: "rotateX",
          panY: "rotateY",
          homeZoomLevel: 20,
          homeGeoPoint: {
            longitude: parseFloat(
              document.getElementById("cc_map_lon").textContent
            ),
            latitude: parseFloat(
              document.getElementById("cc_map_lat").textContent
            ),
          },
        })
      );
    }
  } else {
    var chart = root.container.children.push(
      am5map.MapChart.new(root, {
        panX: "rotateX",
        panY: "rotateY",
        homeZoomLevel: 5,
        homeGeoPoint: {
          longitude: -4.999559822762455,
          latitude: 40.46383610634605,
        },
      })
    );
  }

  var zoomControl = chart.set("zoomControl", am5map.ZoomControl.new(root, {}));

  zoomControl.minusButton.setAll({
    stroke: am5.color(0xfff),
  });
  zoomControl.plusButton.setAll({
    stroke: am5.color(0xfff),
  });

  zoomControl.minusButton.get("background").setAll({
    fill: am5.color(0x6f9b3a),
    fillOpacity: 0.7,
    stroke: am5.color(0x6f9b3a),
    strokeWidth: 2,
    strokeOpacity: 0.3,
  });
  zoomControl.minusButton
    .get("background")
    .states.create("hover", {})
    .setAll({
      fill: am5.color(0x6f9b3a),
      fillOpacity: 1,
    });

  zoomControl.minusButton
    .get("background")
    .states.create("down", {})
    .setAll({
      fill: am5.color(0x6f9b3a),
      fillOpacity: 1,
    });

  zoomControl.plusButton.get("background").setAll({
    fill: am5.color(0x6f9b3a),
    fillOpacity: 0.7,
    stroke: am5.color(0x6f9b3a),
    strokeWidth: 2,
    strokeOpacity: 0.3,
  });

  zoomControl.plusButton
    .get("background")
    .states.create("hover", {})
    .setAll({
      fill: am5.color(0x6f9b3a),
      fillOpacity: 1,
    });

  zoomControl.plusButton
    .get("background")
    .states.create("down", {})
    .setAll({
      fill: am5.color(0x6f9b3a),
      fillOpacity: 1,
    });

  var polygonSeries = chart.series.push(
    am5map.MapPolygonSeries.new(root, {
      geoJSON: am5geodata_region_world_europeHigh,
    })
  );

  polygonSeries.events.on("datavalidated", function () {
    chart.goHome();
  });

  polygonSeries.mapPolygons.template.setAll({
    fill: am5.color(0xdadada),
  });

  var pointSeries = chart.series.push(
    am5map.ClusteredPointSeries.new(root, {})
  );

  pointSeries.set("clusteredBullet", function (root) {
    var container = am5.Container.new(root, {
      cursorOverStyle: "pointer",
    });

    var circle1 = container.children.push(
      am5.Circle.new(root, {
        radius: 8,
        tooltipY: 0,
        fill: am5.color(0xf33c12),
      })
    );

    var circle2 = container.children.push(
      am5.Circle.new(root, {
        radius: 12,
        fillOpacity: 0.3,
        tooltipY: 0,
        fill: am5.color(0xf33c12),
      })
    );

    var circle3 = container.children.push(
      am5.Circle.new(root, {
        radius: 16,
        fillOpacity: 0.3,
        tooltipY: 0,
        fill: am5.color(0xf33c12),
      })
    );

    var label = container.children.push(
      am5.Label.new(root, {
        centerX: am5.p50,
        centerY: am5.p50,
        fill: am5.color(0xffffff),
        populateText: true,
        fontSize: "8",
        text: "{value}",
      })
    );

    container.events.on("click", function (e) {
      pointSeries.zoomToCluster(e.target.dataItem);
    });

    return am5.Bullet.new(root, {
      sprite: container,
    });
  });

  // Create regular bullets
  pointSeries.bullets.push(function () {
    var circle = am5.Circle.new(root, {
      radius: 6,
      tooltipY: 0,
      fill: am5.color(0xf33c12),
      tooltipText: "{title}",
    });

    return am5.Bullet.new(root, {
      sprite: circle,
    });
  });

  fetch("/api/armazens/coordenadas/" + countryCode, {
    method: "GET",
    headers: {
      Accept: "application/json",
    },
  })
    .then((response) => response.json())
    .then((response) => {
      var cities = response["data"];

      for (var i = 0; i < cities.length; i++) {
        var city = cities[i];
        addCity(
          parseFloat(city.longitude),
          parseFloat(city.latitude),
          city.title
        );
      }

      function addCity(longitude, latitude, title) {
        pointSeries.data.push({
          geometry: { type: "Point", coordinates: [longitude, latitude] },
          title: title,
        });
      }
      chart.appear(1000, 100);
    });
}
