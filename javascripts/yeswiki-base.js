/* Author: Florian Schmitt <florian@outils-reseaux.org> under GPL licence */

var DATATABLE_OPTIONS = {
  //responsive: true,
  paging: false,
  language: {
    sProcessing: _t("DATATABLES_PROCESSING"),
    sSearch: _t("DATATABLES_SEARCH"),
    sLengthMenu: _t("DATATABLES_LENGTHMENU"),
    sInfo: _t("DATATABLES_INFO"),
    sInfoEmpty: _t("DATATABLES_INFOEMPTY"),
    sInfoFiltered: _t("DATATABLES_INFOFILTERED"),
    sInfoPostFix: "",
    sLoadingRecords: _t("DATATABLES_LOADINGRECORDS"),
    sZeroRecords: _t("DATATABLES_ZERORECORD"),
    sEmptyTable: _t("DATATABLES_EMPTYTABLE"),
    oPaginate: {
      sFirst: _t("FIRST"),
      sPrevious: _t("PREVIOUS"),
      sNext: _t("NEXT"),
      sLast: _t("LAST"),
    },
    oAria: {
      sSortAscending: _t("DATATABLES_SORTASCENDING"),
      sSortDescending: _t("DATATABLES_SORTDESCENDING"),
    },
  },
  fixedHeader: {
    header: true,
    footer: false,
  },
  dom:
    "<'row'<'col-sm-6'l><'col-sm-6'f>>" +
    "<'row'<'col-sm-12'tr>>" +
    "<'row'<'col-sm-6'i><'col-sm-6'<'pull-right'B>>>",
  buttons: [
    {
      extend: "copy",
      className: "btn btn-default",
      text: '<i class="far fa-copy"></i> ' + _t("COPY"),
    },
    {
      extend: "csv",
      className: "btn btn-default",
      text: '<i class="fas fa-file-csv"></i> CSV',
    },
    {
      extend: "print",
      className: "btn btn-default",
      text: '<i class="fas fa-print"></i> ' + _t("PRINT"),
    },
    // {
    //   extend: 'colvis',
    //   text: _t('DATATABLES_COLS_TO_DISPLAY')
    // },
  ],
};

function toastMessage(
  message,
  duration = 3000,
  toastClass = "alert alert-secondary-1"
) {
  var $toast = $(
    '<div class="toast-message"><div class="' +
      toastClass +
      '">' +
      message +
      "</div></div>"
  );
  $("body").after($toast);
  $toast.css("top", $("#yw-topnav").outerHeight(true) + 20 + "px");
  $toast.css("opacity", 1);
  setTimeout(function () {
    $toast.css("opacity", 0);
  }, duration);
  setTimeout(function () {
    $toast.remove();
  }, duration + 300);
  $toast.addClass("visible");
}
// polyfill placeholder
(function ($) {
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
    var yesWikiModalHtml =
      '<div class="modal-dialog' +
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
      "</div>";
    if ($modal.length == 0) {
      $("body").append(
        '<div class="modal fade" id="YesWikiModal">' +
          yesWikiModalHtml +
          "</div>"
      );
      $modal = $("#YesWikiModal");
    } else {
      $modal.html(yesWikiModalHtml);
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
      var modalTitle = $modal.find(".modal-header h3");
      if (modalTitle.length > 0) {
        if (modalTitle[0].innerText == 0) {
          modalTitle[0].innerHTML =
            '<a href="' + link + '">' + link.substr(0, 128) + "</a>";
        } else {
          modalTitle[0].innerHTML =
            '<a href="' + link + '">' + modalTitle[0].innerText + "</a>";
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
      $("#yw-modal-iframe").on("load", function () {
        $("#yw-modal-loading").hide();
      });
    } else {
      // incomingurl can be usefull (per example for deletepage handler)
      try {
        let url = document.createElement("a");
        url.href = link;
        let queryString = url.search;
        if (!queryString || queryString.length == 0) {
          var separator = "?";
        } else {
          var separator = "&";
        }
        link +=
          separator +
          "incomingurl=" +
          encodeURIComponent(window.location.toString());
      } catch (e) {}
      // AJAX Request for javascripts
      var xhttp = new XMLHttpRequest();
      xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
          var xmlString = this.responseText;
          var doc = new DOMParser().parseFromString(xmlString, "text/html");
          // find scripts
          var res = doc.scripts;
          var l = res.length;
          var i;
          for (i = 0; i < l; i++) {
            var src = res[i].getAttribute("src");
            if (src) {
              var selection = document.querySelectorAll(
                'script[src="' + src + '"]'
              );
              if (!selection || selection.length == 0) {
                // append script and load it only if not present
                document.body.appendChild(document.importNode(res[i]));
                $.getScript(src);
              }
            } else {
              var script = res[i].innerHTML;
              // select all script of current page without src
              var selection = document.scripts;
              var selLenght = selection.length;
              var j;
              for (j = 0; j < selLenght; j++) {
                if (
                  !selection[j].hasAttribute("src") &&
                  script != selection[j].innerHTML
                ) {
                  var newScript = document.importNode(res[i]);
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
              var selection = document.querySelector(
                'link[href="' + href + '"]'
              );
              if (!selection || selection.length == 0) {
                // append link
                document.body.appendChild(document.importNode(importedCSS[i]));
              }
            }
          }
          // AJAX Request for content
          $modal
            .find(".modal-body")
            .load(link + " .page", function (response, status, xhr) {
              $(document).trigger("yw-modal-open");
              return false;
            });
        }
      };
      xhttp.open("GET", link, true);
      xhttp.send();
    }
    $modal
      .modal({
        keyboard: false,
      })
      .modal("show")
      .on("hidden hidden.bs.modal", function () {
        $modal.remove();
      });

    return false;
  }
  $(document).on("click", "a.modalbox, .modalbox a", openModal);

  // on change l'icone de l'accordeon
  $(".accordion-trigger").on("click", function () {
    if ($(this).next().find(".collapse").hasClass("in")) {
      $(this).find(".arrow").html("&#9658;");
    } else {
      $(this).find(".arrow").html("&#9660;");
    }
  });

  // on enleve la fonction doubleclic dans des cas ou cela pourrait etre indesirable
  $(".no-dblclick, form, .page a, button, .dropdown-menu").on(
    "dblclick",
    function (e) {
      return false;
    }
  );

  // deplacer les fenetres modales en bas de body pour eviter que des styles s'appliquent
  $(".modal").appendTo(document.body);

  // Remove hidden div by ACL
  $(".remove-this-div-on-page-load").remove();

  // Pour l'apercu des themes, on recharge la page avec le theme selectionne
  $("#form_theme_selector select").on("change", function () {
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
    let presetValue = "";
    if (typeof getActivePreset == "function") {
      let key = getActivePreset();
      if (key) {
        presetValue = "&preset=" + key;
      }
    }

    var url = window.location.toString();
    let separator = "&";
    if (
      wiki &&
      typeof wiki.baseUrl == "string" &&
      !wiki.baseUrl.includes("?")
    ) {
      // rewrite mode
      separator = "?";
    }
    var urlAux = url.split(separator + "theme=");
    window.location =
      urlAux[0] +
      separator +
      "theme=" +
      $("#changetheme").val() +
      "&squelette=" +
      $("#changesquelette").val() +
      "&style=" +
      $("#changestyle").val() +
      presetValue;
  });

  /* tooltips */
  $("[data-toggle='tooltip']").tooltip();

  // moteur de recherche utilisé dans un template
  $('a[href="#search"]').on("click", function (e) {
    e.preventDefault();
    $(this).siblings('#search').addClass("open");
    $(this).siblings('#search').find(".search-query").focus();
  });

  $("#search, #search button.close-search").on("click keyup", function (e) {
    if (
      e.target == this ||
      $(e.target).hasClass("close-search") ||
      e.keyCode == 27
    ) {
      $(this).removeClass("open");
    }
  });

  // se souvenir des tabs navigués
  $.fn.historyTabs = function () {
    var that = this;
    window.addEventListener("popstate", function (event) {
      if (event.state) {
        $(that)
          .filter('[href="' + event.state.url + '"]')
          .tab("show");
      }
    });
    return this.each(function (index, element) {
      $(element).on("show.bs.tab", function () {
        var stateObject = {
          url: $(this).attr("href"),
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
  $(".navbar").on("dblclick", function (e) {
    e.stopPropagation();
    $("body").append(
      '<div class="modal fade" id="YesWikiModal">' +
        '<div class="modal-dialog">' +
        '<div class="modal-content">' +
        '<div class="modal-header">' +
        '<button type="button" class="close" data-dismiss="modal">&times;</button>' +
        "<h3>" +
        _t("NAVBAR_EDIT_MESSAGE") +
        "</h3>" +
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
      .each(function () {
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
              '<i class="fa fa-pencil-alt"></i> ' +
              _t("YESWIKIMODAL_EDIT_MSG") +
              " " +
              pagewiki +
              "</a>"
          );
      });

    $editmodal
      .find(".modal-body")
      .append(
        '<a href="#" data-dismiss="modal" class="btn btn-warning btn-xs btn-block">' +
          +_t("EDIT_OUPS_MSG") +
          "</a>"
      );

    $editmodal
      .modal({
        keyboard: true,
      })
      .modal("show")
      .on("hidden hidden.bs.modal", function () {
        $editmodal.remove();
      });

    return false;
  });

  // AUTO RESIZE IFRAME
  var iframes = $("iframe.auto-resize");
  if (iframes.length > 0) {
    $.getScript("tools/templates/libs/vendor/iframeResizer.min.js")
      .done(function (script, textStatus) {
        iframes.iFrameResize();
      })
      .fail(function (jqxhr, settings, exception) {
        console.log(
          "Error getting script tools/templates/libs/vendor/iframeResizer.min.js",
          exception
        );
      });
  }

  // get the html from a yeswiki page
  function getText(url, link) {
    var html;
    $.get(url, function (data) {
      html = data;
    }).done(function () {
      link.attr("data-content", html);
    });
  }

  $(".modalbox-hover").each(function (index) {
    getText($(this).attr("href") + "/html", $(this));
  });
  $(".modalbox-hover").popover({
    trigger: "hover",
    html: true, // permet d'utiliser du html
    placement: "right", // position de la popover (top ou bottom ou left ou right)
  });

  // ouvrir les liens dans une nouvelle fenetre
  $(".new-window").attr("target", "_blank");
  $(document).on("yw-modal-open",function(){
    $(".new-window:not([target])").attr("target", "_blank");
  });

  // acl switch
  $("#acl-switch-mode")
    .change(function () {
      if ($(this).prop("checked")) {
        // show advanced
        $(".acl-simple").hide().val(null);
        $(".acl-advanced").slideDown();
      } else {
        $(".acl-single-container label").each(function () {
          $(this).after($("select[name=" + $(this).data("input") + "]"));
        });
        $(".acl-simple").show();
        $(".acl-advanced").hide().val(null);
      }
    })
    .trigger("change");

  // tables
  if (typeof $(".table").DataTable === "function") {
    $(".table:not(.prevent-auto-init)").DataTable(DATATABLE_OPTIONS);
  }

  /** comments */
  var $comments = $(".yeswiki-page-comments, #post-comment");

  // ajax post comment
  $comments.on("click", ".btn-post-comment", function (e) {
    e.preventDefault();
    var form = $(this).parent("form");
    var urlpost = form.attr("action");
    $.ajax({
      type: "POST",
      url: urlpost,
      data: form.serialize(),
      dataType: "json",
      success: function (e) {
        form.trigger("reset");
        toastMessage(e.success, 3000, "alert alert-success");
        // we place the new comment in different places if its an answer, a modification or a new comment
        if (form.hasClass('comment-modify')) {
          form.closest('.yw-comment').html($('<div>').html(e.html).find('.yw-comment').html())
          form.remove();
          $('#post-comment').removeClass('hide')
        } else if (form.parent().hasClass('comment-reponses')) {
          form.parent().append(e.html);
          form.remove()
          $('#post-comment').removeClass('hide')
        } else {
          $(".yeswiki-page-comments").append(e.html);
        }

      },
      error: function (e) {
        toastMessage(e.responseJSON.error, 3000, "alert alert-danger");
      },
    });
    return false;
  });

  // ajax answer comment
  $comments.on("click", ".btn-answer-comment", function (e) {
    e.preventDefault()

    var com = $(this).parent().parent()

    // delete temporary forms that may be open
    $('.temporary-form').remove()

    // clone comment form and change some options
    var formAnswer = com.find('.comment-reponses:first')
    $('#post-comment').clone().appendTo(formAnswer) 
    formAnswer.find('form')
      .attr('id', 'form-comment-'+com.data('tag'))
      .removeClass('hide')
      .addClass('temporary-form')
    formAnswer.find('label').remove()
    formAnswer.find('[name="pagetag"]').val(com.data('tag'))
		formAnswer.find('form').append('<button class="btn-cancel-comment btn btn-sm btn-danger">'+_t('CANCEL')+'</button>')
    com.find('.comment-links:first').addClass('hide')
	
    // hide comment form while another comment form is open
    $('#post-comment').addClass('hide') 

    return false;
  });

  // ajax edit comment
  $comments.on('click', '.btn-edit-comment', function (e) {
    e.preventDefault()
    var com = $(this).parent().parent()

    // hide comment while editor is open
    com.find('.comment-html:first').addClass('hide')

    // delete temporary forms that may be open
    $('.temporary-form').remove()

    // clone comment form and change some options
    var formcom = com.find('.form-comment:first')
    $('#post-comment').clone().appendTo(formcom) 
    formcom.find('form')
      .attr('id', 'form-comment-'+com.data('tag'))
      .attr('action', formcom.find('form').attr('action')+'/'+com.data('tag'))
      .removeClass('hide')
      .addClass('temporary-form')
      .addClass('comment-modify')
    formcom.find('label').remove()
    formcom.find('textarea').val(com.find('.comment-body').val())
    formcom.find('[name="pagetag"]').val(com.data('commenton'))
    formcom.find('.btn-post-comment').text(_t('MODIFY'))
		formcom.find('form').append('<button class="btn-cancel-comment btn btn-sm btn-danger">'+_t('CANCEL')+'</button>')
    com.find('.comment-links:first').addClass('hide')
	
    // hide comment form while another comment form is open
    $('#post-comment').addClass('hide') 

    return false;
  });

  // cancel comment edit
  $comments.on('click', '.btn-cancel-comment', function (e) {
    e.preventDefault()
    
    var com = $(this).parent().parent().parent()

    // restore html comment and links
    com.find('.comment-html:first').removeClass('hide')
    com.find('.comment-links:first').removeClass('hide')
    // remove modify comment form
    $('#form-comment-'+com.data('tag')).remove()
    
    // restore comment form
    $('#post-comment').removeClass('hide') 

    return false;
  });

  // ajax delete comment
  $comments.on('click', '.btn-delete-comment', function (e) {
    if (confirm(_t('DELETE_COMMENT_AND_ANSWERS'))) {
      e.preventDefault();
      var link = $(this);
      $.ajax({
        type: "GET",
        url: link.attr('href'),
        dataType: 'json',
        success: function (e) {
          link.parents('.yw-comment').slideUp(250, function() {
            $(this).remove();
          });
          toastMessage(e.success, 3000, 'alert alert-success');
        },
        error: function (e) {
          toastMessage(e.responseJSON.error, 3000, 'alert alert-danger');
        },
      });
    }
    return false;
  });
  // reaction

  // init user reaction count
  $(".reactions-container").each(function (i, val) {
    var userReaction = $(val).find(".user-reaction").length;
    var nbReactionLeft = $(val).find(".max-reaction").text();
    $(val)
      .find(".max-reaction")
      .text(nbReactionLeft - userReaction);
  });
  // handler reaction click
  $(".link-reaction").click(function () {
    var url = $(this).attr("href");
    var data = $(this).data();
    var nb = $(this).find(".reaction-numbers");
    var nbInit = parseInt(nb.text());
    if (url !== "#") {
      if ($(this).hasClass("user-reaction")) {
        // on supprime la reaction
        if (typeof blockReactionRemove !== "undefined" && blockReactionRemove) {
          if (blockReactionRemoveMessage) {
            if (typeof toastMessage == "function") {
              toastMessage(
                blockReactionRemoveMessage,
                3000,
                "alert alert-warning"
              );
            } else {
              alert(blockReactionRemoveMessage);
            }
          }
          return false;
        } else {
          nb.text(nbInit - 1);
          $(this).removeClass("user-reaction");
          var nbReactionLeft = parseFloat(
            $(this).parents(".reactions-container").find(".max-reaction").text()
          );
          $(this)
            .parents(".reactions-container")
            .find(".max-reaction")
            .text(nbReactionLeft + 1);
          $.ajax({
            method: "DELETE",
            url: url+'/'+data.reactionid+'/'+data.id+'/'+data.pagetag+'/'+data.username
          })
          return false;
        }
      } else {
        // on ajoute la reaction si le max n'est pas dépassé
        var nbReactionLeft = parseFloat( $(this).parents(".reactions-container").find(".max-reaction").text());
        if (nbReactionLeft>0) {
          $(this)
            .find(".reaction-numbers")
            .text(nbReactionLeft - 1);
            
          nb.text(nbInit + 1);
          $(this).addClass("user-reaction");
          $(this)
            .parents(".reactions-container")
            .find(".max-reaction")
            .text(nbReactionLeft -1);
          $.ajax({
            method: "POST",
            url: url,
            data: data,
          }).done(function (data) {
            if (data.state == "error") {
              alert(data.errorMessage);
              nb.text(nbInit);
            }
          });
        } else {
          var message = 'Vous n\'avez plus de choix possibles, vous pouvez retirer un choix existant pour changer'
          if (typeof toastMessage == "function") {
            toastMessage(
              message,
              3000,
              "alert alert-warning"
            );
          } else {
            alert(message)
          }
        }
        return false;
      }
    }
  });
})(jQuery);
