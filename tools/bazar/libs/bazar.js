/**
 *
 * javascript for bazar
 *
 * */

$(document).ready(function() {

  //accordeon pour bazarliste
  $('.titre_accordeon').on('click', function() {
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
  $('#markers a').on('click', function() {
    var i = $(this).attr('rel');
    // this next line closes all open infowindows before opening the selected one
    for (x = 0; x < arrInfoWindows.length; x++) {
      arrInfoWindows[ x ].close();
    }
    arrInfoWindows[ i ].open(map, arrMarkers[ i ]);
    $('ul.css-tabs li').remove();
    $('fieldset.tab').each(function(i) {
      $(this).parent('div.BAZ_cadre_fiche').prev('ul.css-tabs').append('<li class=\'liste' + i + '\'><a href="#">' + $(this).find('legend:first').hide().html() + '</a></li>');
    });
    $('ul.css-tabs').tabs('fieldset.tab', {
      onClick: function() {}
    });
  });

  // initialise les tooltips pour l'aide et pour les cartes leaflet
  $('img.tooltip_aide[title], .bazar-marker').each(function() {
    $(this).tooltip({
      animation: true,
      delay: 0,
      position: 'top'
    });
  });

  //on enleve la fonction doubleclic dans le cas d'une page contenant bazar
  $('#formulaire, #map, #calendar, .accordion').bind('dblclick', function(e) {
    return false;
  });

  //permet de gerer des affichages conditionnels, en fonction de balises div
  $('select[id^=\'liste\']').each(function() {
    var id = $(this).attr('id');
    id = id.replace('liste', '');
    $('div[id^=\'' + id + '\']').hide();
    $('div[id=\'' + id + '_' + $(this).val() + '\']').show();
  });
  $('select[id^=\'liste\']').change(function() {
    var id = $(this).attr('id');
    id = id.replace('liste', '');
    $('div[id^=\'' + id + '\']').hide();
    $('div[id=\'' + id + '_' + $(this).val() + '\']').show();
  });

  //choix de l'heure pour une date
  $('.select-allday').change(function() {
    if ($(this).val() === '0') {
      $(this).parent().next('.select-time').removeClass('hide');
    } else if ($(this).val() === '1') {
      $(this).parent().next('.select-time').addClass('hide');
    }
  });

  //============longueur maximale d'un champs textarea
  var textareas = $('textarea[maxlength].form-control');
  // si les textarea contiennent déja quelque chose, on calcule les caractères restants
  textareas.each(function() {
    var $this = $(this);
    var max = $this.attr('maxlength');
    var length = $this.val().length;
    if (length > max) {
      $this.val($this.val().substr(0, max));
    }
    $this.parents('.control-group').find('.charsRemaining').html((max - length));
  });

  // on empeche d'aller au dela de la limite du nombre de caracteres
  textareas.on('keyup', function() {
    var $this = $(this);
    var max = $this.attr('maxlength');
    var length = $this.val().length;
    if (length > max) {
      $this.val($this.val().substr(0, max));
    }
    $this.parents('.control-group').find('.charsRemaining').html((max - length));
  });

  //============bidouille pour que les widgets en flash restent en dessous des éléments en survol===========
  $('object').append('<param value="opaque" name="wmode">');
  $('embed').attr('wmode', 'opaque');

  /* swap open/close side menu icons */
  $('.yeswiki-list-category[data-toggle=collapse]').click(function() {
    // toggle icon
    $(this).find('i').toggleClass('glyphicon-chevron-right glyphicon-chevron-down');
  });

  //============validation formulaire=======================================================================
  //============gestion des dates=======================================================================

  //validation formulaire de saisie
  var inputsreq = $('#formulaire input[required=required]:visible, #formulaire select[required=required]:visible, #formulaire textarea[required=required]:visible').not('#formulaire input.bazar-date[required=required]');
  $('.bouton_sauver').click(function() {
    var atleastonefieldnotvalid = false;
    var atleastonemailfieldnotvalid = false;
    var atleastoneurlfieldnotvalid = false;
    var atleastonecheckboxfieldnotvalid = false;
    var atleastonetagfieldnotvalid = false;

    // il y a des champs requis, on teste la validite champs par champs
    if (inputsreq.length > 0) {
      inputsreq.each(function() {
        if (!($(this).val().length === 0 || $(this).val() === '' || $(this).val() === '0')) {
          $(this).removeClass('invalid');
        } else {
          atleastonefieldnotvalid = true;
          $(this).addClass('invalid');
        }
      });
    }

    // les dates
    $('#formulaire input.bazar-date[required=required]').each(function() {
      if ($(this).val() === '') {
        atleastonefieldnotvalid = true;
        $(this).addClass('invalid');
      } else {
        $(this).removeClass('invalid');
      }
    });

    // les emails
    $('#formulaire input[type=email]').each(function() {
      var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;
      var address = $(this).val();
      if (reg.test(address) === false && !(address === '' && $(this).attr('required') !== 'required')) {
        atleastonemailfieldnotvalid = true;
        $(this).addClass('invalid');
      } else {
        $(this).removeClass('invalid');
      }
    });

    // les urls
    $('#formulaire input[type=url]').each(function() {
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
    $('#formulaire fieldset.chk_required').each(function() {
      var nbchkbox = $(this).find(':checked');
      if (nbchkbox.length === 0) {
        atleastonecheckboxfieldnotvalid = true;
        $(this).addClass('invalid');
      } else {
        $(this).removeClass('invalid');
      }
    });

    // les checkbox des tags
    $('#formulaire [required] .bootstrap-tagsinput').each(function() {
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
        scrollTop: $('#formulaire .invalid').offset().top - 80
      }, 800);
    } else if (atleastonemailfieldnotvalid === true) {
      alert('L\'email saisi n\'est pas valide');
      //on remonte en haut du formulaire
      $('html, body').animate({
        scrollTop: $('#formulaire .invalid').offset().top - 80
      }, 800);
    } else if (atleastoneurlfieldnotvalid === true) {
      alert('L\'url saisie n\'est pas valide, elle doit commencer par http:// et ne pas contenir d\'espaces ou caracteres speciaux');
      //on remonte en haut du formulaire
      $('html, body').animate({
        scrollTop: $('#formulaire .invalid').offset().top - 80
      }, 800);
    } else if (atleastonecheckboxfieldnotvalid === true) {
      alert('Il faut cocher au moins une case a cocher');
      //on remonte en haut du formulaire
      $('html, body').animate({
        scrollTop: $('#formulaire .invalid').offset().top - 80
      }, 800);
    } else if (atleastonetagfieldnotvalid === true) {
      alert('Il faut saisir au moins une entrée pour le champs en autocomplétion');
      //on remonte en haut du formulaire
      $('html, body').animate({
        scrollTop: $('#formulaire .bootstrap-tagsinput.invalid').offset().top - 80
      }, 800);
    } 
    // formulaire validé, on soumet le formulaire
    else {
      $('#formulaire').submit();
    }

    return false;
  });

  //on change le look des champs obligatoires en cas de saisie dedans
  inputsreq.keypress(function(event) {
    if (!($(this).val().length === 0 || $(this).val() === '' || $(this).val() === '0')) {
      $(this).removeClass('invalid');
    } else {
      atleastonefieldnotvalid = true;
      $(this).addClass('invalid');
    }
  });
  //on change le look des champs obligatoires en cas de changement de valeur
  inputsreq.change(function(event) {
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
  var dateinputs = $('input.bazar-date');
  Modernizr.load([{
    test: dateinputs.length === 0,
    nope: 'tools/bazar/libs/vendor/bootstrap-datepicker.js',
    complete: function() {
      if (dateinputs.length > 0) {
        $.fn.datepicker.dates.fr = {
          days: ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'],
          daysShort: ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
          daysMin: ['D', 'L', 'Ma', 'Me', 'J', 'V', 'S', 'D'],
          months: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
          monthsShort: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jui', 'Jul', 'Aou', 'Sep', 'Oct', 'Nov', 'Déc']
        };
        dateinputs.datepicker({
          format: 'yyyy-mm-dd',
          weekStart: 1,
          autoclose: true,
          language: 'fr'
        }).attr('autocomplete', 'off');
      }
    }
  }]);

  // Onglets
  $('.nav-tabs a').click(function(e) {
    e.preventDefault();
    $(this).tab('show');
  });
  // code pour les boutons suivant / precedent
  $('.tab-content .pager a').click(function(e) {
    e.preventDefault();
    var lientab = $(this).attr('href');
    $('.nav-tabs a[href="' + lientab + '"]').tab('show'); // Select tab by name
  });

  $('.tab-pane').removeAttr('style');

  // cocher / decocher tous
  var checkboxselectall = $('.selectall');
  checkboxselectall.click(function(event) {
    var $this = $(this);
    var target = $this.parent().siblings('.yeswiki-checkbox');
    if ($this.data('target')) {
      target = $($this.data('target'));
    }
    if (this.checked) { // check select status
      target.each(function() {
        $(this).find(':checkbox').prop('checked', true);
        checkboxselectall.prop('checked', true);
      });
    } else {
      target.each(function() {
        $(this).find(':checkbox').prop('checked', false);
        checkboxselectall.prop('checked', false);
      });
    }
  });

  // facettes

  // process changes on visible entries according to filters
  function handleFilterClick(selector) {

    $(selector).each(function() {
      var $container = $(this);
      var $nbresults = $('.nb-results', $container);
      var $filters = $('.filter-checkbox', $container);
      var $filterboxes = $('.filter-box', $container);
      var $entries = $('.bazar-entry', $container);
      var select;
      $filters.on('click', function() {
        var tabfilters = Array();
        var i = 0;
        // on filtre les resultat par boite de filtre pour faire l'intersection apres
        $filterboxes.each(function() {
            select = '';
            var first = true;
            var filterschk = $(this).find('.filter-checkbox:checked');
            $.each(filterschk, function(index, checkbox) {
              if (first) {
                select += '[data-' + $(checkbox).attr('name').toLowerCase() + '*=' + $(checkbox).attr('value') + ']';
                first = false;
              } else {
                select += ',[data-' + $(checkbox).attr('name').toLowerCase() + '*=' + $(checkbox).attr('value') + ']';
              }
            });
            var res = $entries.filter(select);

            if (res.length > 0) {
              tabfilters[i] = res;
              i = i + 1;
            }
          });

        // au moins un filtre à actionner
        if (tabfilters.length > 0) {
          // un premier résultat pour le tableau
          var tabres = tabfilters[0].toArray();
          // pour chaque boite de filtre, on fait l'intersection avec la suivante
          $.each(tabfilters, function(index, tab) {
            tabres = tabres.filter(function(n) {
              return tab.toArray().indexOf(n) != -1;
            });
          });

          $entries.hide().filter(tabres).show();
          $entries.parent('.bazar-marker').hide();
          $entries.filter(tabres).parent('.bazar-marker').show();

        } else {
          // pas de filtres: on affiche tout les résultats
          $entries.show();
          $entries.parent('.bazar-marker').show();
        }
        // on compte les résultats visibles
        $nbresults.html($entries.filter(':visible').length);
      });
    });
  }
  handleFilterClick('.facette-container');

  // Tags
  //bidouille pour les typeahead des champs tags
  $(".bootstrap-tagsinput input").on('keypress', function(){
    $(this).attr("size", $(this).val().length + 2);
  });
  $(".bootstrap-tagsinput").on('change', function(){
    $(this).find('input').val('');
  });
  $.extend($.fn.typeahead.Constructor.prototype, {
      val: function(){}
  });
  // on envoie la valeur au submit
  $("#formulaire").on('submit', function() {
      $(this).find('.yeswiki-input-entries, .yeswiki-input-pagetag').each(function(){
          $(this).tagsinput('add', $(this).tagsinput('input').val());
      });
  });
});
