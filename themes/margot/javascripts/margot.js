$(document).ready(function() {
  // replace full calendar icons
  $(".fc-prev-button")
    .html('<span class="fa fa-chevron-left"></span>')
    .prependTo(".fc-toolbar.fc-header-toolbar")
    .removeClass("btn btn-default");
  $(".fc-next-button")
    .html('<span class="fa fa-chevron-right"></span>')
    .appendTo(".fc-toolbar.fc-header-toolbar")
    .removeClass("btn btn-default");

  $(".form-control").each(function() {
    var parent = $(this).closest(".form-group");
    parent.addClass(
      $(this)
        .prop("tagName")
        .toLowerCase()
    );
    parent.addClass($(this).attr("type"));
    if ($(this).hasClass("wiki-textarea")) {
      parent.addClass("wiki-textarea");
      parent.find(".control-label").prependTo(parent.find(".aceditor-toolbar"));
    }
  });
  $(".controls .radio, .controls .checkbox").each(function() {
    var parent = $(this).closest(".form-group");
    parent.addClass("form-control wrapper");
  });

  // ajout du span pour les checkbox/radio oubliés
  $(":checkbox, :radio").each(function() {
    if (
      $(this)
        .next()
        .prop("nodeName") !== "SPAN"
    ) {
      $(this).after("<span></span>");
    }
  });
  // hack pour la ferme a wiki dont l'input hidden cachait le reste
  $("#bf_dossier-wiki")
    .parents(".control-group.email.password")
    .removeClass("hidden");

  $(".tooltip_aide").each(function() {
    var tooltip = $(this).data("original-title");
    var newImage = $(
      "<span class='form-help fa fa-question-circle' title='" +
        tooltip +
        "'></span>"
    );
    $(this)
      .parent()
      .append(newImage);
    // newImage.tooltip();
    $(this).remove();
  });

  $(".bazar-list .panel-collapse")
    .on("hide.bs.collapse", function() {
      $(this)
        .parent()
        .addClass("collapsed");
    })
    .on("show.bs.collapse", function() {
      $(this)
        .parent()
        .removeClass("collapsed");
    });

  $("#search-form + .facette-container").each(function() {
    $(this)
      .siblings("#search-form")
      .prependTo($(this).find(".results-col"));
  });

  window.onresize = resizeNav;
  resizeNav();

  $("#yw-topnav .btn-menu").click(function() {
    $links = $("#yw-topnav .links-container");
    if ($links.is(":visible")) {
      $links.fadeOut(200);
      $("#yw-topnav .menu-backdrop").remove();
    } else {
      $links.fadeIn(200);
      $backdrop = $("<div class='menu-backdrop'></div>");
      $links.before($backdrop);
      $backdrop.click(function(e) {
        $("#yw-topnav .btn-menu").trigger("click");
        e.preventDefault();
        e.stopPropagation();
      });
    }
  });
});

function resizeNav() {
  console.log("resizeNav", $("#yw-topnav").outerHeight());
  $("#yw-header").css("margin-top", $("#yw-topnav").outerHeight() + "px");
}
