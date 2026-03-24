(function ($, Drupal) {
  Drupal.behaviors.Bee = {
    attach: function attach(context) {
      var $context = $(context);

      $context.find("#edit-bws").drupalSetSummary(function (context) {
        var vals = [];
        var $editContext = $(context);
        if ($editContext.find("#edit-bws-bookable").is(":checked")) {
          if (
            $editContext
              .find('input[name="bws[bookable_type][radios]"]:checked')
              .val() == "daily"
          ) {
            vals.push(Drupal.t("Daily Bookings Enabled"));
          }
        } else {
          vals.pop();
        }
        return vals.join(", ");
      });
    },
  };
})(jQuery, Drupal);
