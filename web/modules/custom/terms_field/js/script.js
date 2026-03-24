(function ($, Drupal) {
  if (!$(".terms-field-terms-and-conditions-elements input").is(":checked")) {
    $(".terms-field-terms-and-conditions-elements input").prop(
      "disabled",
      true
    );

    $(".terms-field-terms-and-conditions-elements .container-terms-box").on(
      "scroll",
      chk_scroll
    );
  }

  function chk_scroll(e) {
    var elem = $(e.currentTarget);
    if (elem[0].scrollHeight - elem.scrollTop() == elem.outerHeight()) {
      elem.parent().find("input").prop("disabled", false);
    }
  }
})(jQuery, Drupal);
