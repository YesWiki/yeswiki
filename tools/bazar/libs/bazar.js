/**
 *
 * javascript for bazar
 *
 * */

$(document).ready(function () {

  //accordeon pour bazarliste
  $('.titre_accordeon').on('click', function () {
    if ($(this).hasClass('current')) {
      $(this).removeClass('current');
      $(this).next('div.pane').hide();
    } else {
      $(this).addClass('current');
      $(this).next('div.pane').show();
    }
  });

  //antispam javascript
  $('input[name=antispam]').val('1');

  //carto google
  var divcarto = document.getElementById('map');
  if (divcarto) {
    initialize();
  }

  // clic sur le lien d'une fiche, l'ouvre sur la carto
  $('#markers a').on('click', function () {
    var i = $(this).attr('rel');

    // this next line closes all open infowindows before opening the selected one
    for (x = 0; x < arrInfoWindows.length; x++) {
      arrInfoWindows[x].close();
    }

    arrInfoWindows[i].open(map, arrMarkers[i]);
    $('ul.css-tabs li').remove();
    $('fieldset.tab').each(function (i) {
      $(this)
        .parent('div.BAZ_cadre_fiche')
        .prev('ul.css-tabs')
        .append('<li class=\'liste' + i + '\'><a href="#">'
          + $(this).find('legend:first').hide().html() + '</a></li>');
    });

    $('ul.css-tabs').tabs('fieldset.tab', {
      onClick: function () {},
    });
  });

  // initialise les tooltips pour l'aide et pour les cartes leaflet
  $('img.tooltip_aide[title], .bazar-marker').each(function () {
    $(this).tooltip({
      animation: true,
      delay: 0,
      position: 'top',
    });
  });

  //on enleve la fonction doubleclic dans le cas d'une page contenant bazar
  $('#formulaire, #map, #calendar, .accordion').bind('dblclick', function (e) {
    return false;
  });

  //permet de gerer des affichages conditionnels, en fonction de balises div
  function handleConditionnalListChoice() {
    var id = $(this).attr('id');
    $('div[id^=\'' + id + '\'], div[id^=\'' + id.replace('liste', '') + '\']')
      .not('div[id=\'' + id + '_' + $(this).val() + '\'], div[id=\'' + id.replace('liste', '') + '_' + $(this).val() + '\']').hide()
     .find(':input').val('').removeProp('checked');
    $('div[id=\'' + id + '_' + $(this).val() + '\'], div[id=\'' + id.replace('liste', '') + '_' + $(this).val() + '\']').show();
  }
  function handleConditionnalCheckboxChoice() {
    var id = $(this).attr('id');
    var re = /^([a-zA-Z0-9-_]+)\[([a-zA-Z0-9-_]+)\]$/;
    var m;

    if ((m = re.exec(id)) !== null) {
        if (m.index === re.lastIndex) {
            re.lastIndex++;
        }
    }
    if (m) {
      if ($(this).prop('checked') == true) {
        $('div[id=\'' + m[1] + '_' + m[2] + '\']').show();
      } else {
        $('div[id=\'' + m[1] + '_' + m[2] + '\']').hide()
        .find(':input').val('').removeProp('checked');
      }
    }
  }

  $('select[id^=\'liste\']').each(handleConditionnalListChoice).change(handleConditionnalListChoice);
  $('.element_checkbox[id^=\'checkboxListe\']').each(handleConditionnalCheckboxChoice).change(handleConditionnalCheckboxChoice);

  //choix de l'heure pour une date
  $('.select-allday').change(function () {
    if ($(this).val() === '0') {
      $(this).parent().next('.select-time').removeClass('hide');
    } else if ($(this).val() === '1') {
      $(this).parent().next('.select-time').addClass('hide');
    }
  });

  //============longueur maximale d'un champs textarea
  var $textareas = $('textarea[maxlength].form-control');

  // si les textarea contiennent déja quelque chose, on calcule les caractères restants
  $textareas.each(function () {
    var $this = $(this);
    var max = $this.attr('maxlength');
    var length = $this.val().length;
    if (length > max) {
      $this.val($this.val().substr(0, max));
    }

    $this.parents('.control-group').find('.charsRemaining').html((max - length));
  });

  // on empeche d'aller au dela de la limite du nombre de caracteres
  $textareas.on('keyup', function () {
    var $this = $(this);
    var max = $this.attr('maxlength');
    var length = $this.val().length;
    if (length > max) {
      $this.val($this.val().substr(0, max));
    }

    $this.parents('.control-group').find('.charsRemaining').html((max - length));
  });

  //============ bidouille pour que les widgets en flash restent ===========
  //============ en dessous des éléments en survol ===========
  $('object').append('<param value="opaque" name="wmode">');
  $('embed').attr('wmode', 'opaque');

  /* swap open/close side menu icons */
  $('.yeswiki-list-category[data-toggle=collapse]').click(function () {
    // toggle icon
    $(this).find('i').toggleClass('glyphicon-chevron-right glyphicon-chevron-down');
  });

  //============validation formulaire============================
  //============gestion des dates================================

  //validation formulaire de saisie
  var inputsreq = $('#formulaire input[required=required]:visible, '
    + '#formulaire select[required=required]:visible, '
    + '#formulaire textarea[required=required]:visible')
    .not('#formulaire input.bazar-date[required=required]');

  $('#formulaire').submit(function(e) {
    var atleastonefieldnotvalid = false;
    var atleastonemailfieldnotvalid = false;
    var atleastoneurlfieldnotvalid = false;
    var atleastonecheckboxfieldnotvalid = false;
    var atleastonetagfieldnotvalid = false;

    // il y a des champs requis, on teste la validite champs par champs
    if (inputsreq.length > 0) {
      inputsreq.each(function () {
        if (!($(this).val().length === 0 || $(this).val() === '' || $(this).val() === '0')) {
          $(this).removeClass('invalid');
        } else {
          atleastonefieldnotvalid = true;
          $(this).addClass('invalid');
        }
      });
    }

    // les dates
    $('#formulaire input.bazar-date[required=required]:visible').each(function () {
      if ($(this).val() === '') {
        atleastonefieldnotvalid = true;
        $(this).addClass('invalid');
      } else {
        $(this).removeClass('invalid');
      }
    });

    // les emails
    $('#formulaire input[type=email]:visible').each(function () {
      var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;
      var address = $(this).val();
      if (reg.test(address) === false
        && !(address === '' && $(this).attr('required') !== 'required')) {
        atleastonemailfieldnotvalid = true;
        $(this).addClass('invalid');
      } else {
        $(this).removeClass('invalid');
      }
    });

    // les urls
    $('#formulaire input[type=url]:visible').each(function () {
      var reg = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/;
      var url = $(this).val();
      if (reg.test(url) === false && !(url === '' && $(this).attr('required') !== 'required')) {
        atleastoneurlfieldnotvalid = true;
        $(this).addClass('invalid');
      } else {
        $(this).removeClass('invalid');
      }
    });

    // les checkbox chk_required
    $('#formulaire fieldset.chk_required:visible').each(function () {
      var nbchkbox = $(this).find(':checked');
      if (nbchkbox.length === 0) {
        atleastonecheckboxfieldnotvalid = true;
        $(this).addClass('invalid');
      } else {
        $(this).removeClass('invalid');
      }
    });

    // les checkbox des tags
    $('#formulaire [required] .bootstrap-tagsinput:visible').each(function () {
      var nbtag = $(this).find('.tag');
      if (nbtag.length === 0) {
        atleastonetagfieldnotvalid = true;
        $(this).addClass('invalid');
      } else {
        $(this).removeClass('invalid');
      }
    });

    // affichage des erreurs de validation
    if (atleastonefieldnotvalid === true) {
      alert('Veuillez saisir tous les champs obligatoires (avec une asterisque rouge)');

      //on remonte en haut du formulaire
      $('html, body').animate({
        scrollTop: $('#formulaire .invalid').offset().top - 80,
      }, 500);
    } else if (atleastonemailfieldnotvalid === true) {
      alert('L\'email saisi n\'est pas valide');

      //on remonte en haut du formulaire
      $('html, body').animate({
        scrollTop: $('#formulaire .invalid').offset().top - 80,
      }, 500);

    } else if (atleastoneurlfieldnotvalid === true) {
      alert('L\'url saisie n\'est pas valide, elle doit commencer par http:// '
        + 'et ne pas contenir d\'espaces ou caracteres speciaux');

      //on remonte en haut du formulaire
      $('html, body').animate({
        scrollTop: $('#formulaire .invalid').offset().top - 80,
      }, 500);
    } else if (atleastonecheckboxfieldnotvalid === true) {
      alert('Il faut cocher au moins une case a cocher');

      //on remonte en haut du formulaire
      $('html, body').animate({
        scrollTop: $('#formulaire .invalid').offset().top - 80,
      }, 500);
    } else if (atleastonetagfieldnotvalid === true) {
      alert('Il faut saisir au moins une entrée pour le champs en autocomplétion');

      //on remonte en haut du formulaire
      $('html, body').animate({
        scrollTop: $('#formulaire .bootstrap-tagsinput.invalid').offset().top - 80,
      }, 500);
    }

    // formulaire validé, on soumet le formulaire
    else {
      return true;
    }
    e.preventDefault();
    return false;
  });

  //on change le look des champs obligatoires en cas de saisie dedans
  inputsreq.keypress(function (event) {
    if (!($(this).val().length === 0 || $(this).val() === '' || $(this).val() === '0')) {
      $(this).removeClass('invalid');
    } else {
      atleastonefieldnotvalid = true;
      $(this).addClass('invalid');
    }
  });

  //on change le look des champs obligatoires en cas de changement de valeur
  inputsreq.change(function (event) {
    if (!($(this).val().length === 0 || $(this).val() === '' || $(this).val() === '0')) {
      $(this).removeClass('invalid');
    } else {
      atleastonefieldnotvalid = true;
      $(this).addClass('invalid');
    }
  });

  // bidouille PEAR form
  $('#formulaire').removeAttr('onsubmit');

  // selecteur de dates
  var $dateinputs = $('input.bazar-date');
  Modernizr.load([{
    test: $dateinputs.length === 0,
    nope: 'tools/bazar/libs/vendor/bootstrap-datepicker.js',
    complete: function () {
      if ($dateinputs.length > 0) {
        $.fn.datepicker.dates.fr = {
          days: [
            'Dimanche',
            'Lundi',
            'Mardi',
            'Mercredi',
            'Jeudi',
            'Vendredi',
            'Samedi',
            'Dimanche',
          ],
          daysShort: ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
          daysMin: ['D', 'L', 'Ma', 'Me', 'J', 'V', 'S', 'D'],
          months: [
            'Janvier',
            'Février',
            'Mars',
            'Avril',
            'Mai',
            'Juin',
            'Juillet',
            'Août',
            'Septembre',
            'Octobre',
            'Novembre',
            'Décembre',
          ],
          monthsShort: [
            'Jan',
            'Fév',
            'Mar',
            'Avr',
            'Mai',
            'Jui',
            'Jul',
            'Aou',
            'Sep',
            'Oct',
            'Nov',
            'Déc',
          ],
        };
        $dateinputs.datepicker({
          format: 'yyyy-mm-dd',
          weekStart: 1,
          autoclose: true,
          language: 'fr',
        }).attr('autocomplete', 'off');
      }
    },
  },
  ]);

  // Onglets
  // hack pour les fiches avec tabulations : on change les id pour qu'ils soient uniques
  $('.bazar-entry').each(function (i) {
    $(this).find('[data-toggle="tab"]').each(function () {
      $(this).attr('href', $(this).attr('href') + '-' + i);
    });

    $(this).find('.tab-pane').each(function () {
      $(this).attr('id', $(this).attr('id') + '-' + i);
    });
  });

  // hack pour les boutons suivant precedent dans le formulaire bazar
  $('#formulaire .tab-content [data-toggle="tab"]').click(function () {
     $('#formulaire .nav-tabs .active').removeClass('active');
     $('#formulaire .nav-tabs').find('[href="' + $(this).attr('href') + '"]').parent().addClass('active');
     $('#formulaire .nav-tabs a[href="' + $(this).attr('href') + '"]').tab('show');
     $('html, body').animate({
       scrollTop: $('#formulaire').offset().top - 80,
     }, 500);
  });
  $('.BAZ_cadre_fiche .tab-content [data-toggle="tab"]').click(function () {
     var $this = $(this);
     $('.BAZ_cadre_fiche .nav-tabs .active').removeClass('active');
     $('.BAZ_cadre_fiche .nav-tabs').find('[href="' + $(this).attr('href') + '"]').parent().addClass('active');
     $('.BAZ_cadre_fiche .nav-tabs a[href="' + $(this).attr('href') + '"]').tab('show');
  });

  // cocher / decocher tous
  var $checkboxselectall = $('.selectall');
  $checkboxselectall.click(function (event) {
    var $this = $(this);
    var target = $this.parents('.controls').find('.yeswiki-checkbox');
    if ($this.data('target')) {
      $target = $($this.data('target'));
    }

    if (this.checked) { // check select status
      $target.each(function () {
        $(this).find(':checkbox').prop('checked', true);
        $checkboxselectall.prop('checked', true);
      });
    } else {
      $target.each(function () {
        $(this).find(':checkbox').prop('checked', false);
        $checkboxselectall.prop('checked', false);
      });
    }
  });

  // facettes

  // recuperer un parametre donné de l'url
  function getURLParameter(name) {
    return decodeURIComponent(
      (new RegExp('[?|&]' + name + '=' + '([^&;]+?)(&|#|;|$)').exec(location.search) || [, ''])[1]
         .replace(/\+/g, '%20')) || null;
  }

  // modifier un parametre de l'url pour les modifier dynamiquement
  function changeURLParameter(name, value) {
    if (getURLParameter(name) == null) {
      var s = location.search;
      var urlquery = s.replace('&' + name + '=', '').replace('?' + name + '=', '');
      if (value !== '') {
        if (s !== '') {
          urlquery += '&' + name + '=' + value;
        } else {
          urlquery += '?' + name + '=' + value;
        }
      }

      history.pushState({ filter: true }, null, urlquery);

      // pour les url dans une iframe
      if (window.frameElement && window.frameElement.nodeName == 'IFRAME') {
        var iframeurlquery = window.top.location.search
          .replace('&' + name + '=', '') + '&' + name + '=' + value;
        window.top.history.pushState({ filter: true }, null, iframeurlquery);
      }
    } else {
      var s = location.search;
      //console.log('s', s, s !== '', decodeURIComponent(s));
      //console.log('value', value);
      var urlquery;
      if (value !== '') {
        if (s !== '') {
          urlquery = decodeURIComponent(s).replace(
            new RegExp('&' + name + '=' + '([^&;]+?)(&|#|;|$)'),
            '&' + name + '=' + value
          );
          //console.log('location.search', s, urlquery);
        } else {
          urlquery = '?' + name + '=' + value;
        } //console.log('location.search vide', s, urlquery);
      } else {
        urlquery = decodeURIComponent(s).replace(
          new RegExp('[?|&]' + name + '=' + '([^&;]+?)(&|#|;|$)'),
          ''
        );
      }

      history.pushState({ filter: true }, null, urlquery);

      // pour les url dans une iframe
      if (window.frameElement && window.frameElement.nodeName == 'IFRAME') {
        var iframeurlquery = decodeURIComponent(window.top.location.search).replace(
          new RegExp('[?|&]' + name + '=' + '([^&;]+?)(&|#|;|$)'),
          '&' + name + '=' + value
        );
        window.top.history.pushState({ filter: true }, null, iframeurlquery);
      }

      return location.search;
    }
  }

  // activer les filtres des facettes
  function updateFilters(e) {
    var tabfilters = Array();
    var i = 0;
    var newquery = '';
    var select;

    // on filtre les resultat par boite de filtre pour faire l'intersection apres
    e.data.$filterboxes.each(function () {
      select = '';
      var first = true;
      var filterschk = $(this).find('.filter-checkbox:checked');
      $.each(filterschk, function (index, checkbox) {
        // les valeurs sont mis en cache
        var name = $(checkbox).attr('name');
        var val = $(checkbox).attr('value');
        var attr = 'data-' + name.toLowerCase();
        if (first) {
          // si ce n'est pas le premier appel, on ajoute un | pour separer les query
          if (newquery !== '') {
            newquery += '|';
          }
          newquery += name + '=' + val;
          first = false;
        } else {
          newquery += ',' + val;
          select += ',';
        }
        // La requete de selection prend pour les champs non multiples :
        // - exactement la valeur de l'attribut html
        // Pour les champs multiples :
        // - soit les attributs commencant par la valeur suivie d'une virgule
        // - soit les attributs finissant par la valeur avec une virgule avant
        // - soit les attributs contenant la valeur entouree de virgules
        select += '[' + attr + '~="' + val + '"],[' + attr + '$=",' + val + '"],[' + attr + '^="' + val + ',"],[' + attr + '*=",' + val + ',"]';
      });
      var res = e.data.$entries.filter(select);

      if (res.length > 0) {
        tabfilters[i] = res;
        i = i + 1;
      }
    });

    // on applique les changements a l'url
    changeURLParameter('facette', newquery);

    // on ajuste les liens vers les formulaires d'export
    if (newquery !== '') {
      $('.export-links a').each(
        function() {
          var link = $(this).attr('href');
          var queryexists = new RegExp('&query=' + '([^&;]+?)(&|#|;|$)').exec(link) || null;
          if (queryexists == null) {
            $(this).attr('href', link+'&query='+newquery);
          } else {
            var queryinit = $('#queryinit').val();
            if (queryinit) { newquery = queryinit+'|'+newquery}
            $(this).attr(
              'href',
              link.replace(new RegExp('&query=' + '([^&;]+?)(&|#|;|$)'), '&query=' + newquery)
            );
          }
        }
      );
    }

    // au moins un filtre à actionner
    if (tabfilters.length > 0) {
      // un premier résultat pour le tableau
      var tabres = tabfilters[0].toArray();

      // pour chaque boite de filtre, on fait l'intersection avec la suivante
      $.each(tabfilters, function (index, tab) {
        tabres = tabres.filter(function (n) {
          return tab.toArray().indexOf(n) != -1;
        });
      });

      e.data.$entries.hide().filter(tabres).show();
      e.data.$entries.parent('.bazar-marker').hide();
      e.data.$entries.filter(tabres).parent('.bazar-marker').show();

    } else {
      // pas de filtres: on affiche tout les résultats
      e.data.$entries.show();
      e.data.$entries.parent('.bazar-marker').show();
    }

    // on compte les résultats visibles
    e.data.$nbresults.html(e.data.$entries.filter(':visible').length);
  }

  // process changes on visible entries according to filters
  $('.facette-container').each(function () {
    var $container = $(this);
    var $filters = $('.filter-checkbox', $container);
    var data = {
      $nbresults: $('.nb-results', $container),
      $filterboxes: $('.filter-box', $container),
      $entries: $('.bazar-entry', $container),
    };
    $filters.on('click', data, updateFilters);
    jQuery(window).on('load', data, updateFilters);
  });

  // gestion de l'historique : on reapplique les filtres
  window.onpopstate = function(e) {
    if (e.state && e.state.filter) {
      $('.facette-container').each(function () {
        var $this = $(this);
        $(this).find('input:checkbox').prop('checked', false);
        var urlparamfacette = getURLParameter('facette');
        var tabfacette = urlparamfacette.split('|');
        for (var i = 0; i < tabfacette.length; i++) {
          var tabfilter = tabfacette[i].split('=');
          if (tabfilter[1] !== '') {
            tabvalues = tabfilter[1].split(',');
            for (var j = 0; j < tabvalues.length; j++) {
              $('#' + tabfilter[0] + tabvalues[j]).prop('checked', true);
            }
          }
        }

        var $container = $(this);
        var $filters = $('.filter-checkbox', $container);
        var data = {
          $nbresults: $('.nb-results', $container),
          $filterboxes: $('.filter-box', $container),
          $entries: $('.bazar-entry', $container),
        };
        e.data = data;
        updateFilters(e);
      });
    }
  };

  // Tags
  //bidouille pour les typeahead des champs tags
  $('.bootstrap-tagsinput input').on('keypress', function () {
    $(this).attr('size', $(this).val().length + 2);
  });

  $('.bootstrap-tagsinput').on('change', function () {
    $(this).find('input').val('');
  });

  $.extend($.fn.typeahead.Constructor.prototype, {
    val: function () {},
  });

  // on envoie la valeur au submit
  $('#formulaire').on('submit', function () {
    $(this).find('.yeswiki-input-entries, .yeswiki-input-pagetag').each(function () {
      $(this).tagsinput('add', $(this).tagsinput('input').val());
    });
  });
});
