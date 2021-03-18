/* Author: Florian Schmitt <florian@outils-reseaux.org> under GPL licence */

var DATATABLE_OPTIONS = {
  //responsive: true,
  paging: false,
  language: {
    "sProcessing": "Traitement en cours...",
    "sSearch": "Rechercher&nbsp;:",
    "sLengthMenu": "Afficher _MENU_ &eacute;l&eacute;ments",
    "sInfo": "Affichage de l'&eacute;l&eacute;ment _START_ &agrave; _END_ sur _TOTAL_ &eacute;l&eacute;ments",
    "sInfoEmpty": "Affichage de l'&eacute;l&eacute;ment 0 &agrave; 0 sur 0 &eacute;l&eacute;ment",
    "sInfoFiltered": "(filtr&eacute; de _MAX_ &eacute;l&eacute;ments au total)",
    "sInfoPostFix": "",
    "sLoadingRecords": "Chargement en cours...",
    "sZeroRecords": "Aucun &eacute;l&eacute;ment &agrave; afficher",
    "sEmptyTable": "Aucune donn&eacute;e disponible dans le tableau",
    "oPaginate": {
      "sFirst": "Premier",
      "sPrevious": "Pr&eacute;c&eacute;dent",
      "sNext": "Suivant",
      "sLast": "Dernier"
    },
    "oAria": {
      "sSortAscending": ": activer pour trier la colonne par ordre croissant",
      "sSortDescending": ": activer pour trier la colonne par ordre d&eacute;croissant"
    }
  },
  fixedHeader: {
    header: true,
    footer: false
  },
  dom: "<'row'<'col-sm-6'l><'col-sm-6'f>>" +
  "<'row'<'col-sm-12'tr>>" +
  "<'row'<'col-sm-6'i><'col-sm-6'<'pull-right'B>>>",
  buttons: [
    {
      extend: 'copy',
      text: '<i class="far fa-copy"></i> Copier'
    },
    {
      extend: 'csv',
      text: '<i class="fas fa-file-csv"></i> CSV'
    },
    {
      extend: 'print',
      text: '<i class="fas fa-print"></i> Imprimer'
    },
    // {
    //   extend: 'colvis',
    //   text: 'Colonnes à afficher'
    // },

  ]
}


function toastMessage(message, duration = 3000, toastClass = 'alert alert-secondary-1') {
  var $toast = $('<div class="toast-message"><div class="' + toastClass + '">' + message + '</div></div>');
  $('body').after($toast);
  $toast.css('top', $('#yw-topnav').outerHeight(true) + 20 + 'px');
  $toast.css('opacity', 1);
  setTimeout(function() { $toast.css('opacity', 0) }, duration);
  setTimeout(function() { $toast.remove() }, (duration + 300) );
  $toast.addClass('visible');
}
// polyfill placeholder
(function($) {
  // gestion des classes actives pour les menus
  $("a.active-link")
    .parent()
    .addClass("active-list")
    .parents("ul")
    .prev("a")
    .addClass("active-parent-link")
    .parent()
    .addClass("active-list");

  // fenetres modales
  function openModal(e) {
    e.stopPropagation();
    e.preventDefault();
    var $this = $(this);
    var text = $this.attr("title") || "";
    var size = " " + $this.data("size");
    var iframe = $this.data("iframe");
    if (text.length > 0) {
      text = "<h3>" + $.trim(text) + "</h3>";
    } else {
      text = "<h3></h3>";
    }

    var $modal = $("#YesWikiModal");
    var yesWikiModalHtml = '<div class="modal-dialog' +
      size +
      '">' +
      '<div class="modal-content">' +
      '<div class="modal-header">' +
      '<button type="button" class="close" data-dismiss="modal">&times;</button>' +
      text +
      "</div>" +
      '<div class="modal-body">' +
      "</div>" +
      "</div>" +
      "</div>" ;
    if ($modal.length == 0) {
      $("body").append(
        '<div class="modal fade" id="YesWikiModal">' +
        yesWikiModalHtml +
        "</div>"
      );
      $modal = $("#YesWikiModal");
    } else {
      $modal.html(yesWikiModalHtml) ;
    }

    var link = $this.attr("href");
    if (/\.(gif|jpg|jpeg|tiff|png)$/i.test(link)) {
      $modal
        .find(".modal-body")
        .html(
          '<img class="center-block img-responsive" src="' +
            link +
            '" alt="image" />'
        );
    } else if (iframe === 1) {
      var modalTitle = $modal.find(".modal-header h3") ;
      if (modalTitle.length > 0 ) {
        if (modalTitle[0].innerText == 0) {
          modalTitle[0].innerHTML = '<a href="'+link+'">'
            + link.substr(0,128)
            + '</a>';
        } else {
          modalTitle[0].innerHTML = '<a href="'+link+'">'
            + modalTitle[0].innerText
            + '</a>';
        }
      }
      $modal
        .find(".modal-body")
        .html(
          '<span id="yw-modal-loading" class="throbber"></span>' +
            '<iframe id="yw-modal-iframe" src="' +
            link +
            '" referrerpolicy="no-referrer"></iframe>'
        );
      $("#yw-modal-iframe").on("load", function() {
        $("#yw-modal-loading").hide();
      });
    } else {
      // incomingurl can be usefull (per example for deletepage handler)
      try {
        link += "&incomingurl=" + encodeURIComponent(window.location.toString());
      } catch (e) {}
      // AJAX Request
      var xhttp = new XMLHttpRequest();
      xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          var xmlString = this.responseText;
          var doc = new DOMParser().parseFromString(xmlString, "text/html");
          var page = doc.querySelector(".page").innerHTML ;
          $modal.find(".modal-body").html(page) ;
          // find scripts
          var res = doc.scripts;
          var l = res.length;
          var i;
          for (i = 0; i < l; i++) {
            var src = res[i].getAttribute("src");
            if (src) {
              var selection = document.querySelectorAll('script[src="'+src+'"]') ;
              if (!selection || selection.length == 0) {
                // append script and load it only if not present
                document.body.appendChild(document.importNode(res[i]));
                $.getScript(src);
              }
            } else {
              var script=res[i].innerHTML ;
              // select all script of current page without src
              var selection = document.scripts ;
              var selLenght = selection.length;
              var j;
              for (j = 0; j < selLenght; j++) {
                if (!selection[j].hasAttribute('src') && script != selection[j].innerHTML){
                  var newScript = document.importNode(res[i]) ;
                  document.body.appendChild(newScript);
                }
              }
            }
          } 
          // find css
          var importedCSS = doc.querySelectorAll('link[rel="stylesheet"]');
          var l = importedCSS.length;
          var i;
          for (i = 0; i < l; i++) {
            var href = importedCSS[i].getAttribute("href");
            if (href) {
              var selection = document.querySelector('link[href="'+href+'"]') ;
              if (!selection ||selection.length == 0) {
                // append link
                document.body.appendChild(document.importNode(importedCSS[i]));
              }
            }
          }

          $(document).trigger("yw-modal-open");
        }
      };
      xhttp.open("GET", link, true);
      xhttp.send();
    }
    $modal
      .modal({
        keyboard: false
      })
      .modal("show")
      .on("hidden hidden.bs.modal", function() {
        $modal.remove();
      });

    return false;
  }
  $(document).on("click", "a.modalbox, .modalbox a", openModal);

  // on change l'icone de l'accordeon
  $(".accordion-trigger").on("click", function() {
    if (
      $(this)
        .next()
        .find(".collapse")
        .hasClass("in")
    ) {
      $(this)
        .find(".arrow")
        .html("&#9658;");
    } else {
      $(this)
        .find(".arrow")
        .html("&#9660;");
    }
  });

  // on enleve la fonction doubleclic dans des cas ou cela pourrait etre indesirable
  $(".no-dblclick, form, .page a, button, .dropdown-menu").on(
    "dblclick",
    function(e) {
      return false;
    }
  );

  // deplacer les fenetres modales en bas de body pour eviter que des styles s'appliquent
  $(".modal").appendTo(document.body);

  // Remove hidden div by ACL
  $('.remove-this-div-on-page-load').remove();

  // Pour l'apercu des themes, on recharge la page avec le theme selectionne
  $("#form_theme_selector select").on("change", function() {
    if ($(this).attr("id") === "changetheme") {
      // On change le theme dynamiquement
      var val = $(this).val();

      // pour vider la liste
      var squelette = $("#changesquelette")[0];
      squelette.options.length = 0;
      var i;
      for (i = 0; i < tab1[val].length; i++) {
        o = new Option(tab1[val][i], tab1[val][i]);
        squelette.options[squelette.options.length] = o;
      }

      var style = $("#changestyle")[0];
      style.options.length = 0;
      for (i = 0; i < tab2[val].length; i++) {
        o = new Option(tab2[val][i], tab2[val][i]);
        style.options[style.options.length] = o;
      }
    }

    var url = window.location.toString();
    var urlAux = url.split("&theme=");
    window.location =
      urlAux[0] +
      "&theme=" +
      $("#changetheme").val() +
      "&squelette=" +
      $("#changesquelette").val() +
      "&style=" +
      $("#changestyle").val();
  });

  /* tooltips */
  $("[data-toggle='tooltip']").tooltip();

  // detecte quand on scrolle en dessus de la barre horizontale, afin de la fixer en haut
  var $topnav = $("#yw-topnav.fixable");
  if ($topnav.length > 0) {
    var topoffset = $topnav.data("offset") || $topnav.offset().top;
    $topnav.affix({
      offset: topoffset
    });
  }

  // moteur de recherche utilisé dans un template
  $('a[href="#search"]').on("click", function(e) {
    e.preventDefault();
    $("#search").addClass("open");
    $("#search .search-query").focus();
  });

  $("#search, #search button.close-search").on("click keyup", function(e) {
    if (
      e.target == this ||
      $(e.target).hasClass("close-search") ||
      e.keyCode == 27
    ) {
      $(this).removeClass("open");
    }
  });

  // se souvenir des tabs navigués
  $.fn.historyTabs = function() {
    var that = this;
    window.addEventListener("popstate", function(event) {
      if (event.state) {
        $(that)
          .filter('[href="' + event.state.url + '"]')
          .tab("show");
      }
    });
    return this.each(function(index, element) {
      $(element).on("show.bs.tab", function() {
        var stateObject = {
          url: $(this).attr("href")
        };

        if (window.location.hash && stateObject.url !== window.location.hash) {
          window.history.pushState(
            stateObject,
            document.title,
            window.location.pathname +
              window.location.search +
              $(this).attr("href")
          );
        } else {
          window.history.replaceState(
            stateObject,
            document.title,
            window.location.pathname +
              window.location.search +
              $(this).attr("href")
          );
        }
      });
      if (!window.location.hash && $(element).is(".active")) {
        // Shows the first element if there are no query parameters.
        $(element).tab("show");
      } else if ($(this).attr("href") === window.location.hash) {
        $(element).tab("show");
      }
    });
  };
  $('a[data-toggle="tab"]').historyTabs();

  // double clic
  $(".navbar").on("dblclick", function(e) {
    e.stopPropagation();
    $("body").append(
      '<div class="modal fade" id="YesWikiModal">' +
        '<div class="modal-dialog">' +
        '<div class="modal-content">' +
        '<div class="modal-header">' +
        '<button type="button" class="close" data-dismiss="modal">&times;</button>' +
        "<h3>Editer une zone du menu horizontal</h3>" +
        "</div>" +
        '<div class="modal-body">' +
        "</div>" +
        "</div>" +
        "</div>" +
        "</div>"
    );

    var $editmodal = $("#YesWikiModal");
    $(this)
      .find(".include")
      .each(function() {
        var href = $(this)
          .attr("ondblclick")
          .replace("document.location='", "")
          .replace("';", "");
        var pagewiki = href
          .replace("/edit", "")
          .replace("http://yeswiki.dev/wakka.php?wiki=", "");
        $editmodal
          .find(".modal-body")
          .append(
            '<a href="' +
              href +
              '" class="btn btn-default btn-block">' +
              '<i class="fa fa-pencil-alt"></i> Editer la page ' +
              pagewiki +
              "</a>"
          );
      });

    $editmodal
      .find(".modal-body")
      .append(
        '<a href="#" data-dismiss="modal" class="btn btn-warning btn-xs btn-block">' +
          "En fait, je ne voulais pas double-cliquer...</a>"
      );

    $editmodal
      .modal({
        keyboard: true
      })
      .modal("show")
      .on("hidden hidden.bs.modal", function() {
        $editmodal.remove();
      });

    return false;
  });

  // AUTO RESIZE IFRAME
  var iframes = $("iframe.auto-resize");
  if (iframes.length > 0) {
    $.getScript("tools/templates/libs/vendor/iframeResizer.min.js")
      .done(function(script, textStatus) {
        iframes.iFrameResize();
      })
      .fail(function(jqxhr, settings, exception) {
        console.log(
          "Error getting script tools/templates/libs/vendor/iframeResizer.min.js",
          exception
        );
      });
  }

  // get the html from a yeswiki page
  function getText(url, link) {
    var html;
    $.get(url, function(data) {
      html = data;
    }).done(function() {
      link.attr("data-content", html);
    });
  }

  $(".modalbox-hover").each(function(index) {
    getText($(this).attr("href") + "/html", $(this));
  });
  $(".modalbox-hover").popover({
    trigger: "hover",
    html: true, // permet d'utiliser du html
    placement: "right" // position de la popover (top ou bottom ou left ou right)
  });

  // ouvrir les liens dans une nouvelle fenetre
  $(".new-window").attr("target", "_blank");

  // acl switch
  $("#acl-switch-mode")
    .change(function() {
      if ($(this).prop("checked")) {
        // show advanced
        $(".acl-simple")
          .hide()
          .val(null);
        $(".acl-advanced").slideDown();
      } else {
        $(".acl-single-container label").each(function() {
          $(this).after($("select[name=" + $(this).data("input") + "]"));
        });
        $(".acl-simple").show();
        $(".acl-advanced")
          .hide()
          .val(null);
      }
    })
    .trigger("change");

  // tables
  if (typeof $(".table").DataTable === "function") {
    $(".table:not(.prevent-auto-init)").DataTable(DATATABLE_OPTIONS);
  }
})(jQuery);
