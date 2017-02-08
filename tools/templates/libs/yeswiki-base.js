/* Author: Florian Schmitt <florian@outils-reseaux.org> under GPL licence */

// polyfill placeholder
(function($) {

  // gestion des classes actives pour les menus
  $('a.active-link').parent().addClass('active-list').parents('ul').prev('a')
    .addClass('active-parent-link').parent().addClass('active-list');

    // fenetres modales
    function openModal(e) {
      e.stopPropagation();
      e.preventDefault();
      var $this = $(this);
      var text = $this.attr('title');
      var size = ' ' + $this.data('size');
      var iframe = $this.data('iframe');
      if (text.length > 0) {
        text = '<h3>' + $.trim(text) + '</h3>';
      } else {
        text = '<h3></h3>';
      }

      $('body').append('<div class="modal fade" id="YesWikiModal">' +
        '<div class="modal-dialog' + size + '">' +
        '<div class="modal-content">' +
        '<div class="modal-header">' +
        '<button type="button" class="close" data-dismiss="modal">&times;</button>' +
        text +
        '</div>' +
        '<div class="modal-body">' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>');

      var link = $this.attr('href');
      var $modal = $('#YesWikiModal');
      if ((/\.(gif|jpg|jpeg|tiff|png)$/i).test(link)) {
        $modal
          .find('.modal-body')
          .html('<img class="center-block img-responsive" src="' + link + '" alt="image" />');
      } else if (iframe === 1) {
        $modal
          .find('.modal-body')
          .html('<span id="yw-modal-loading" class="throbber"></span>' +
            '<iframe id="yw-modal-iframe" src="' + link + '""></iframe>');
        $('#yw-modal-iframe').on('load', function() {
          $('#yw-modal-loading').hide();
        });
      } else {
        $modal.find('.modal-body').load(link + ' .page', function(response, status, xhr) {
          return false;
        });
      }
      $modal.modal({
        keyboard: false,
      }).modal('show').on('hidden hidden.bs.modal', function() {
        $modal.remove();
      });

      return false;
    }
    $(document).on('click', 'a.modalbox, .modalbox a', openModal);

  // on change l'icone de l'accordeon
  $('.accordion-trigger').on('click', function() {
    if ($(this).next().find('.collapse').hasClass('in')) {
      $(this).find('.arrow').html('&#9658;');
    } else {
      $(this).find('.arrow').html('&#9660;');
    }
  });

  // on enleve la fonction doubleclic dans des cas ou cela pourrait etre indesirable
  $('.no-dblclick, form, .page a, button, .dropdown-menu').on('dblclick', function(e) {
    return false;
  });

  // deplacer les fenetres modales en bas de body pour eviter que des styles s'appliquent
  $('.modal').appendTo(document.body);

  // Pour l'apercu des themes, on recharge la page avec le theme selectionne
  $('#form_theme_selector select').on('change', function() {
    if ($(this).attr('id') === 'changetheme') {
      // On change le theme dynamiquement
      var val = $(this).val();

      // pour vider la liste
      var squelette = $('#changesquelette')[0];
      squelette.options.length = 0;
      var i;
      for (i = 0; i < tab1[val].length; i++) {
        o = new Option(tab1[val][i], tab1[val][i]);
        squelette.options[squelette.options.length] = o;
      }

      var style = $('#changestyle')[0];
      style.options.length = 0;
      for (i = 0; i < tab2[val].length; i++) {
        o = new Option(tab2[val][i], tab2[val][i]);
        style.options[style.options.length] = o;
      }
    }

    var url = window.location.toString();
    var urlAux = url.split('&theme=');
    window.location = urlAux[0] + '&theme=' + $('#changetheme').val() +
      '&squelette=' + $('#changesquelette').val() + '&style=' + $('#changestyle').val();
  });

  /* tooltips */
  $('[data-toggle=\'tooltip\']').tooltip();

  // detecte quand on scrolle en dessus de la barre horizontale, afin de la fixer en haut
  var $topnav = $('#yw-topnav.fixable');
  if ($topnav.length > 0) {
    var topoffset = $topnav.offset().top;
    $topnav.affix({
      offset: topoffset,
    });
  }

  // moteur de recherche utilisé dans un template
  $('a[href="#search"]').on('click', function(e) {
    e.preventDefault();
    $('#search').addClass('open');
    $('#search .search-query').focus();
  });

  $('#search, #search button.close-search').on('click keyup', function(e) {
    if (e.target == this || $(e.target).hasClass('close-search') || e.keyCode == 27) {
      $(this).removeClass('open');
    }
  });

  // se souvenir des tabs navigués
  $.fn.historyTabs = function() {
    var that = this;
    window.addEventListener('popstate', function(event) {
      if (event.state) {
        $(that).filter('[href="' + event.state.url + '"]').tab('show');
      }
    });
    return this.each(function(index, element) {
      $(element).on('show.bs.tab', function() {
        var stateObject = {
          'url': $(this).attr('href')
        };

        if (window.location.hash && stateObject.url !== window.location.hash) {
          window.history.pushState(stateObject, document.title, window.location.pathname + window.location.search + $(this).attr('href'));
        } else {
          window.history.replaceState(stateObject, document.title, window.location.pathname + window.location.search + $(this).attr('href'));
        }
      });
      if (!window.location.hash && $(element).is('.active')) {
        // Shows the first element if there are no query parameters.
        $(element).tab('show');
      } else if ($(this).attr('href') === window.location.hash) {
        $(element).tab('show');
      }
    });
  };
  $('a[data-toggle="tab"]').historyTabs();

  // double clic
  $('.navbar').on('dblclick', function(e) {
    e.stopPropagation();
    $('body').append('<div class="modal fade" id="YesWikiModal">' +
      '<div class="modal-dialog">' +
      '<div class="modal-content">' +
      '<div class="modal-header">' +
      '<button type="button" class="close" data-dismiss="modal">&times;</button>' +
      '<h3>Editer une zone du menu horizontal</h3>' +
      '</div>' +
      '<div class="modal-body">' +
      '</div>' +
      '</div>' +
      '</div>' +
      '</div>');

    var $editmodal = $('#YesWikiModal');
    $(this).find('.include').each(function() {
      var href = $(this).attr('ondblclick')
        .replace('document.location=\'', '')
        .replace('\';', '');
      var pagewiki = href.replace('/edit', '').replace('http://yeswiki.dev/wakka.php?wiki=', '');
      $editmodal
        .find('.modal-body')
        .append('<a href="' + href + '" class="btn btn-default btn-block">' +
          '<i class="glyphicon glyphicon-pencil"></i> Editer la page ' + pagewiki + '</a>');

    });

    $editmodal
      .find('.modal-body')
      .append('<a href="#" data-dismiss="modal" class="btn btn-warning btn-xs btn-block">' +
        'En fait, je ne voulais pas double-cliquer...</a>');

    $editmodal.modal({
        keyboard: true,
      })
      .modal('show')
      .on('hidden hidden.bs.modal', function() {
        $editmodal.remove();
      });

    return false;

  });

  // AUTO RESIZE IFRAME
  var iframes = $('iframe.auto-resize');
  if (iframes.length > 0) {
    $.getScript( 'tools/templates/libs/vendor/iframeResizer.min.js' )
    .done(function( script, textStatus ) {
      iframes.iFrameResize();
    })
    .fail(function( jqxhr, settings, exception ) {
      console.log('Error getting script tools/templates/libs/vendor/iframeResizer.min.js', exception);
    });
  }

})(jQuery);
