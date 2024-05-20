/**
 *
 * javascript for bazar
 *
 * */

$(document).ready(() => {
  // accordeon pour bazarliste
  $('.titre_accordeon').on('click', function() {
    if ($(this).hasClass('current')) {
      $(this).removeClass('current')
      $(this).next('div.pane').hide()
    } else {
      $(this).addClass('current')
      $(this).next('div.pane').show()
    }
  })

  // antispam javascript
  $('input[name=antispam]').val('1')

  // carto google
  const divcarto = document.getElementById('map')
  if (divcarto) {
    initialize()
  }

  // clic sur le lien d'une fiche, l'ouvre sur la carto
  $('#markers a').on('click', function() {
    const i = $(this).attr('rel')

    // this next line closes all open infowindows before opening the selected one
    for (x = 0; x < arrInfoWindows.length; x++) {
      arrInfoWindows[x].close()
    }

    arrInfoWindows[i].open(map, arrMarkers[i])
    $('ul.css-tabs li').remove()
    $('fieldset.tab').each(function(i) {
      $(this)
        .parent('div.BAZ_cadre_fiche')
        .prev('ul.css-tabs')
        .append(`<li class='liste${i}'><a href="#">${
          $(this).find('legend:first').hide().html()}</a></li>`)
    })

    $('ul.css-tabs').tabs('fieldset.tab', { onClick() {} })
  })

  // initialise les tooltips pour l'aide et pour les cartes leaflet
  $('img.tooltip_aide[title], .bazar-marker').each(function() {
    $(this).tooltip({
      animation: true,
      delay: 0,
      position: 'top'
    })
  })

  // on enleve la fonction doubleclic dans le cas d'une page contenant bazar
  $('#formulaire, #map, #calendar, .accordion').bind('dblclick', (e) => false)

  function emptyChildren(element) {
    if (typeof ConditionsChecking === 'undefined') {
      // backward compatibility old system (not clean) TODO remove it when ConditionsChecking is sure to be installed (even in local cahe)
      $(element).find(':input').val('').removeProp('checked')
    } else {
      ConditionsChecking.emptyChildren(element)
    }
  }

  // TODO : when conditionschecking is the only system (ex: for ectoplasme) remove the followings line to manage
  // conditions by the old way

  // permet de gerer des affichages conditionnels, en fonction de balises div
  function handleConditionnalListChoice() {
    const id = $(this).attr('id')
    $(`div[id^='${id}'], div[id^='${id.replace('liste', '')}']`)
      .not(`div[id='${id}_${$(this).val()}'], div[id='${id.replace('liste', '')}_${$(this).val()}']`).hide()
      .each(function() {
        emptyChildren(this)
      })
    $(`div[id='${id}_${$(this).val()}'], div[id='${id.replace('liste', '')}_${$(this).val()}']`).show()
  }
  function handleConditionnalRadioChoice() {
    const id = $(this).attr('id')
    const shortId = id.substr(0, id.length - $(this).val().toString().length - 1)
    $(`div[id^='${shortId}']`)
      .not(`div[id='${id}']`).hide()
      .each(function() {
        emptyChildren(this)
      })
    $(`div[id='${id}']`).show()
  }
  function handleConditionnalCheckboxChoice() {
    const id = $(this).attr('id')
    const re = /^([a-zA-Z0-9-_]+)\[([a-zA-Z0-9-_]+)\]$/
    let m

    if ((m = re.exec(id)) !== null) {
      if (m.index === re.lastIndex) {
        re.lastIndex++
      }
    }
    if (m) {
      if ($(this).prop('checked') == true) {
        $(`div[id='${m[1]}_${m[2]}']:not(.conditional_inversed_checkbox)`).show()
        $(`div[id='${m[1]}_${m[2]}'].conditional_inversed_checkbox`).hide()
          .each(function() {
            emptyChildren(this)
          })
      } else {
        $(`div[id='${m[1]}_${m[2]}']:not(.conditional_inversed_checkbox)`).hide()
          .each(function() {
            emptyChildren(this)
          })
        $(`div[id='${m[1]}_${m[2]}'].conditional_inversed_checkbox`).show()
      }
    }
  }

  $('select[id^=\'liste\']').each(handleConditionnalListChoice).change(handleConditionnalListChoice)
  $('input.element_radio').each(handleConditionnalRadioChoice).change(handleConditionnalRadioChoice)
  $('.element_checkbox[id^=\'checkboxListe\']').each(handleConditionnalCheckboxChoice).change(handleConditionnalCheckboxChoice)

  // choix de l'heure pour une date
  $('.select-allday').change(function() {
    if ($(this).val() === '0') {
      $(this).parent().next('.select-time').removeClass('hide')
    } else if ($(this).val() === '1') {
      $(this).parent().next('.select-time').addClass('hide')
    }
  })

  //= ===========longueur maximale d'un champs textarea
  const $textareas = $('textarea[maxlength].form-control')

  // si les textarea contiennent déja quelque chose, on calcule les caractères restants
  $textareas.each(function() {
    const $this = $(this)
    const max = $this.attr('maxlength')
    const { length } = $this.val()
    if (length > max) {
      $this.val($this.val().substr(0, max))
    }

    $this.parents('.control-group').find('.charsRemaining').html((max - length))
  })

  // on empeche d'aller au dela de la limite du nombre de caracteres
  $textareas.on('keyup', function() {
    const $this = $(this)
    const max = $this.attr('maxlength')
    const { length } = $this.val()
    if (length > max) {
      $this.val($this.val().substr(0, max))
    }

    $this.parents('.control-group').find('.charsRemaining').html((max - length))
  })

  // éviter la validation du formulaire en pressant la touche Entrée
  document.querySelectorAll('form#formulaire .control-group.form-group input.form-control[type=text]').forEach((item) => {
    item.addEventListener(
      'keydown',
      (event) => {
        if (event.key === 'Enter') {
          event.preventDefault()
          event.stopPropagation()
          return true
        }
      },
      true
    )
  })

  //= =========== bidouille pour que les widgets en flash restent ===========
  //= =========== en dessous des éléments en survol ===========
  $('object').append('<param value="opaque" name="wmode">')
  $('embed').attr('wmode', 'opaque')

  //= ===========validation formulaire============================
  //= ===========gestion des dates================================

  // validation formulaire de saisie
  const requirementHelper = {
    requiredInputs: [],
    textInputsWithPattern: [],
    error: -1, // error contain the index of the first error (-1 = no error)
    errorMessage: '',
    errorPattern: -1,
    errorMessagePattern: '',
    filterVisibleInputs(key = 'requiredInputs') {
      this[key] = (this[key]).filter(function() {
        let inputVisible = $(this).filter(':visible')
        if ((
          $(this).prop('tagName') == 'TEXTAREA' && ($(this).hasClass('wiki-textarea') || $(this).hasClass('summernote'))
        ) || $(this).siblings('.bootstrap-tagsinput').length > 0) {
          inputVisible = $(this).parent().filter(':visible')
        }
        if (typeof inputVisible !== 'undefined' && inputVisible.length > 0) {
          return true
        }
        const notVisibleParents = $(this).parentsUntil(':visible')
        if (typeof notVisibleParents === 'undefined' || notVisibleParents.length == 0) {
          return false
        }
        // check if visible in a tab
        if ($(this).parentsUntil(':visible')
          .filter(function() {
            return $(this).css('display') == 'none'
                && $(this).attr('role') != 'tabpanel'
          }).length == 0) {
          return true
        }
        return false
      })
    },
    getInputType(input) {
      if ($(input).hasClass('bazar-date')) {
        return 'date'
      }
      if ($(input).hasClass('chk_required')) {
        return 'checkbox'
      }
      if ($(input).hasClass('geocode-input')) {
        return 'geocode'
      }
      if ($(input).hasClass('radio_required')) {
        return 'radio'
      }
      if ($(input).siblings('.bootstrap-tagsinput').length > 0) {
        return 'tags'
      }
      if ($(input).attr('type') == 'email') {
        return 'email'
      }
      if ($(input).attr('type') == 'url') {
        return 'url'
      }
      if ($(input).attr('type') == 'range') {
        return 'range'
      }
      if ($(input).prop('tagName') == 'SELECT') {
        return 'select'
      }
      if ($(input).prop('tagName') == 'TEXTAREA') {
        if ($(input).hasClass('wiki-textarea')) {
          return 'wikitextarea'
        }
        if ($(input).hasClass('summernote')) {
          return 'summernote'
        }
        return 'textarea'
      }
      return 'default'
    },
    updateError(index) {
      if (this.error == -1) {
        this.error = index
      }
    },
    updateErrorMessage(message) {
      if (this.error == -1) {
        this.errorMessage = message
      }
    },
    dateChecking(input) {
      if ($(input).val() === '') {
        this.updateErrorMessage(_t('BAZ_FORM_REQUIRED_FIELD'))
        return false
      }
      return true
    },
    rangeChecking(input) {
      if ($(input).val() === $(input).data('default')) {
        this.updateErrorMessage(_t('BAZ_FORM_REQUIRED_FIELD'))
        return false
      }
      return true
    },
    emailChecking(input) {
      const reg = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/ // regex that works for 99,99%, following RFC 5322
      if ($(input).prop('required') && !this.defaultChecking(input)) {
        return false
      } if ($(input).val() != '' && reg.test($(input).val()) === false) {
        this.updateErrorMessage(_t('BAZ_FORM_INVALID_EMAIL'))
        return false
      }
      return true
    },
    urlChecking(input) {
      const reg = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/
      if ($(input).prop('required') && !this.defaultChecking(input)) {
        return false
      } if ($(input).val() != '' && reg.test($(input).val()) === false) {
        this.updateErrorMessage(_t('BAZ_FORM_INVALID_URL'))
        return false
      }
      return true
    },
    selectChecking(input) {
      return this.defaultChecking(input)
    },
    textareaChecking(input) {
      return this.defaultChecking(input)
    },
    wikitextareaChecking(input) {
      const value = $(input).data('aceditor').getValue()
      if (value.length === 0 || value === '') {
        this.updateErrorMessage(_t('BAZ_FORM_REQUIRED_FIELD'))
        $(input).parent().addClass('invalid')
        return false
      }
      $(input).parent().removeClass('invalid')
      return true
    },
    summernoteChecking(input) {
      if ($(input).summernote('isEmpty')) {
        $(input).closest('.form-control.textarea.summernote').addClass('invalid')
        this.updateErrorMessage(_t('BAZ_FORM_REQUIRED_FIELD'))
        return false
      }
      $(input).closest('.form-control.textarea.summernote').removeClass('invalid')
      return true
    },
    checkboxChecking(input) {
      const nbelems = $(input).find('input:checked')
      const parentToInvalid = $(input).closest('.form-group.input-checkbox')
      if (nbelems.length === 0) {
        this.updateErrorMessage(_t('BAZ_FORM_EMPTY_CHECKBOX'))
        $(parentToInvalid).addClass('invalid')
        return false
      }
      $(parentToInvalid).removeClass('invalid')
      return true
    },
    radioChecking(input) {
      const nbelems = $(input).find('input:checked')
      const parentToInvalid = $(input).closest('.form-group.input-radio')
      if (nbelems.length === 0) {
        this.updateErrorMessage(_t('BAZ_FORM_EMPTY_RADIO'))
        $(parentToInvalid).addClass('invalid')
        return false
      }
      $(parentToInvalid).removeClass('invalid')
      return true
    },
    tagsChecking(input) {
      const bootstrapBaseDiv = $(input).siblings('.bootstrap-tagsinput')
      const nbelems = $(bootstrapBaseDiv).find('.tag')
      if (nbelems.length === 0) {
        this.updateErrorMessage(_t('BAZ_FORM_EMPTY_AUTOCOMPLETE'))
        $(bootstrapBaseDiv).addClass('invalid')
        return false
      }
      $(bootstrapBaseDiv).removeClass('invalid')
      return true
    },
    geocodeChecking(input) {
      if (!$(input).find('#bf_latitude').val()) {
        this.updateErrorMessage(_t('BAZ_FORM_EMPTY_GEOLOC'))
        return false
      }
      return true
    },
    defaultChecking(input) {
      if ($(input).val().length === 0 || $(input).val() === '') {
        this.updateErrorMessage(_t('BAZ_FORM_REQUIRED_FIELD'))
        return false
      }
      return true
    },
    checkInput(input, saveError, index) {
      const inputType = this.getInputType(input)
      if (typeof this[`${inputType}Checking`] !== 'function') {
        $(input).addClass('invalid')
        this.updateErrorMessage(`Not possible to check field : unknown function requirementHelper.${inputType}Checking() !`)
        if (saveError) {
          this.updateError(index)
        }
      } else if (!(this[`${inputType}Checking`](input))) {
        $(input).addClass('invalid')
        if (saveError) {
          this.updateError(index)
        }
      } else {
        $(input).removeClass('invalid')
      }
    },
    checkPattern(input, index) {
      if ($(input)[0]) {
        const val = $(input).val()
        const element = $(input)[0]
        if (val.length > 0) {
          if (!element.checkValidity()) {
            if (this.errorPattern == -1) {
              this.errorMessagePattern = _t('BAZ_FORM_INVALID_TEXT')
              this.errorPattern = index
            }
          }
        }
      }
    },
    checkInputs() {
      for (let index = 0; index < this.requiredInputs.length; index++) {
        const input = this.requiredInputs[index]
        this.checkInput(input, true, index)
      }
      for (let index = 0; index < this.textInputsWithPattern.length; index++) {
        const input = this.textInputsWithPattern[index]
        this.checkPattern(input, index)
      }
    },
    displayErrorMessage() {
      alert(this.errorMessage)
    },
    scrollToFirstinputInError(type = 'error') {
      const error = this[type] ?? -1
      if (error > -1) {
        // TODO afficher l'onglet en question
        // on remonte en haut du formulaire
        let input = this.requiredInputs[error]
        if ($(input).filter(':visible').length == 0) {
          // panel ?
          const panel = $(input).parentsUntil(':visible').last()
          if ($(panel).attr('role') == 'tabpanel') {
            $(`a[href="#${$(panel).attr('id')}"][role=tab]`).first().click()
          }
          if ($(input).filter(':visible').length == 0) {
            input = $(input).closest(':visible')
          }
        }
        $('html, body').animate({ scrollTop: $(input).offset().top - 80 }, 500)
      }
    },
    initTextInputsWithPattern(form) {
      this.textInputsWithPattern = $(form).find('input[type=text][pattern]')
      this.errorPattern = -1
    },
    initRequiredInputs(form) {
      this.requiredInputs = $(form).find(
        'input[required],'
        + 'select[required],'
        + 'textarea[required],'
        + ':not(.prev-holder) input[type=email],'
        + ':not(.prev-holder) input[type=url],'
        + '.chk_required,'
        + '.radio_required,'
        + '.geocode-input.required'
      )
      this.error = -1
    },
    run(form) {
      this.initRequiredInputs(form)
      this.initTextInputsWithPattern(form)
      this.filterVisibleInputs('requiredInputs')
      this.filterVisibleInputs('textInputsWithPattern')
      this.checkInputs()
      if (this.error > -1) {
        this.displayErrorMessage()
        this.scrollToFirstinputInError('error')
        return false
      }
      if (this.errorPattern > -1) {
        alert(this.errorMessagePattern)
        this.scrollToFirstinputInError('errorPattern')
        return false
      }
      return true
    },
    runWhenUpdated(target, reqChecking) {
      reqChecking.checkInput(target, false, 0)
    },
    inputInitlistener(input) {
      const reqChecking = this
      $(input).keypress((event) => {
        reqChecking.runWhenUpdated(event.target, reqChecking)
      })
      $(input).change((event) => {
        reqChecking.runWhenUpdated(event.target, reqChecking)
      })
    },
    summernoteInitlistener(input) {
      const reqChecking = this
      $(input).on('summernote.change', (event) => {
        reqChecking.runWhenUpdated(event.target, reqChecking)
      })
    },
    wikitextareaInitlistener(input) {
      const reqChecking = this
      const aceditor = $(input).data('aceditor')
      aceditor.on('change', (event) => {
        reqChecking.runWhenUpdated(input, reqChecking)
      })
    },
    checkboxInitlistener(input) {
      const reqChecking = this
      const checkboxes = $(input).find('input[type=checkbox]')
      $(checkboxes).change((event) => {
        reqChecking.runWhenUpdated($(event.target).closest('.chk_required'), reqChecking)
      })
    },
    radioInitlistener(input) {
      const reqChecking = this
      const radioButtons = $(input).find('input[type=radio]')
      $(radioButtons).change((event) => {
        reqChecking.runWhenUpdated($(event.target).closest('.radio_required'), reqChecking)
      })
    },
    initListeners() {
      this.initRequiredInputs($('#formulaire'))
      for (let index = 0; index < this.requiredInputs.length; index++) {
        const input = this.requiredInputs[index]
        const inputType = this.getInputType(input)
        if (['default', 'select', 'textarea', 'tags'].indexOf(inputType) > -1) {
          this.inputInitlistener(input)
        } else if (['summernote', 'wikitextarea', 'checkbox', 'radio'].indexOf(inputType) > -1) {
          this[`${inputType}Initlistener`](input)
        }
      }
    }
  }

  requirementHelper.initListeners()

  $('#formulaire').submit(function(e) {
    $(this).addClass('submitted')

    try {
      if (requirementHelper.run(this)) {
        // formulaire validé, on soumet le formulaire
        // mais juste avant on change le comportement du bouton pour éviter les validations multiples
        $(this).find('.form-actions button[type=submit]').each(function() {
          $(this).attr('disabled', true)
          $(this).addClass('submit-disabled')
          $(this).attr('title', _t('BAZ_SAVING'))
          const button = $(this)
          setTimeout(() => {
            // on réactive le bouton au bout de 10s juste pour permettre de forcer une nouvelle validation si jamais ça a planté
            $(button).removeAttr('disabled')
          }, 10000)
        })
        return true
      }
    } catch (error) {
      console.warn(error.message)
    }
    e.preventDefault()
    return false
  })

  // bidouille PEAR form
  $('#formulaire').removeAttr('onsubmit')

  // selecteur de dates
  const $dateinputs = $('.bazar-date')

  // test pour verifier si le browser gere l'affichage des dates
  // var input = document.createElement('input');
  // input.setAttribute('type','date');
  // var notADateValue = 'not-a-date';
  // input.setAttribute('value', notADateValue);

  // if ($dateinputs.length > 0 && (input.value == notADateValue)) {
  if ($dateinputs.length > 0) {
    $.fn.datepicker.dates.fr = {
      days: ['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY']
        .map((day) => _t(day)),
      daysShort: ['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY']
        .map((day) => _t(`BAZ_DATESHORT_${day}`)),
      daysMin: ['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY']
        .map((day) => _t(`BAZ_DATEMIN_${day}`)),
      months: ['JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER']
        .map((month) => _t(month)),
      monthsShort: ['JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER']
        .map((month) => _t(`BAZ_DATESHORT_${month}`))
    }
    $dateinputs.datepicker({
      format: 'yyyy-mm-dd',
      weekStart: 1,
      autoclose: true,
      language: 'fr'
    }).attr('autocomplete', 'off')
  }

  // If start_date is greater than en_date, set end_date to start_date
  $startDate = $('#formulaire #bf_date_debut_evenement')
  $endDate = $('#formulaire #bf_date_fin_evenement')
  if ($startDate && $endDate) {
    $startDate.change(() => {
      if (new Date($startDate.val()) > new Date($endDate.val())) $endDate.val($startDate.val())
    })
  }

  // Onglets
  // hack pour les fiches avec tabulations : on change les id pour qu'ils soient uniques
  $('.bazar-entry').each(function(i) {
    $(this).find('[data-toggle="tab"]').each(function() {
      $(this).attr('href', `${$(this).attr('href')}-${i}`)
    })

    $(this).find('.tab-pane').each(function() {
      $(this).attr('id', `${$(this).attr('id')}-${i}`)
    })
  })

  // cocher / decocher tous
  const checkboxselectall = $('.selectall')
  checkboxselectall.click(function(event) {
    const $this = $(this)
    let target = $this.parents('.controls').find('.yeswiki-checkbox')
    if ($this.data('target')) {
      target = $($this.data('target'))
    }

    if (this.checked) { // check select status
      target.each(function() {
        $(this).find(':checkbox').prop('checked', true)
        $(this).prop('checked', true)
      })
    } else {
      target.each(function() {
        $(this).find(':checkbox').prop('checked', false)
        $(this).prop('checked', false)
      })
    }
  })

  // facettes

  // recuperer un parametre donné de l'url
  function getURLParameter(name) {
    return decodeURIComponent(
      (new RegExp(`[?|&]${name}=` + '([^&;]+?)(&|#|;|$)').exec(location.search) || [, ''])[1]
        .replace(/\+/g, '%20')
    ) || null
  }

  // modifier un parametre de l'url pour les modifier dynamiquement
  function changeURLParameter(name, value) {
    if (getURLParameter(name) == null) {
      var s = location.search
      var urlquery = s.replace(`&${name}=`, '').replace(`?${name}=`, '')
      if (value !== '') {
        if (s !== '') {
          urlquery += `&${name}=${value}`
        } else {
          urlquery += `?${name}=${value}`
        }
      }

      history.pushState({ filter: true }, null, urlquery)

      // pour les url dans une iframe
      if (window.frameElement && window.frameElement.nodeName == 'IFRAME') {
        var iframeurlquery = `${window.top.location.search
          .replace(`&${name}=`, '')}&${name}=${value}`
        window.top.history.pushState({ filter: true }, null, iframeurlquery)
      }
    } else {
      var s = location.search
      // console.log('s', s, s !== '', decodeURIComponent(s));
      // console.log('value', value);
      var urlquery
      if (value !== '') {
        if (s !== '') {
          // console.log(s);
          urlquery = decodeURIComponent(s).replace(
            new RegExp(`&${name}=` + '([^&;]+?)(&|#|;|$)'),
            `&${name}=${value}`
          )
          // console.log('location.search', s, urlquery);
        } else {
          urlquery = `?${name}=${value}`
        } // console.log('location.search vide', s, urlquery);
      } else {
        // console.log(s);
        urlquery = decodeURIComponent(s).replace(
          new RegExp(`[?|&]${name}=` + '([^&;]+?)(&|#|;|$)'),
          ''
        )
      }

      history.pushState({ filter: true }, null, urlquery)

      // pour les url dans une iframe
      if (window.frameElement && window.frameElement.nodeName == 'IFRAME') {
        var iframeurlquery = decodeURIComponent(window.top.location.search).replace(
          new RegExp(`[?|&]${name}=` + '([^&;]+?)(&|#|;|$)'),
          `&${name}=${value}`
        )
        window.top.history.pushState({ filter: true }, null, iframeurlquery)
      }

      return location.search
    }
  }

  // activer les filtres des facettes
  function updateFilters(e) {
    const tabfilters = Array()
    let i = 0
    let newquery = ''
    let select

    // on filtre les resultat par boite de filtre pour faire l'intersection apres
    e.data.$filterboxes.each(function() {
      select = ''
      let first = true
      const filterschk = $(this).find('.filter-checkbox:checked')
      $.each(filterschk, (index, checkbox) => {
        // les valeurs sont mis en cache
        const name = $(checkbox).attr('name')
        const val = $(checkbox).attr('value')
        const attr = `data-${name.toLowerCase()}`
        if (first) {
          // si ce n'est pas le premier appel, on ajoute un | pour separer les query
          if (newquery !== '') {
            newquery += '|'
          }
          newquery += `${name}=${val}`
          first = false
        } else {
          newquery += `,${val}`
          select += ','
        }
        // La requete de selection prend pour les champs non multiples :
        // - exactement la valeur de l'attribut html
        // Pour les champs multiples :
        // - soit les attributs commencant par la valeur suivie d'une virgule
        // - soit les attributs finissant par la valeur avec une virgule avant
        // - soit les attributs contenant la valeur entouree de virgules
        select += `[${attr}~="${val}"],[${attr}$=",${val}"],[${attr}^="${val},"],[${attr}*=",${val},"]`
      })
      const res = e.data.$entries.filter(select)

      if (res.length > 0) {
        tabfilters[i] = res
        i += 1
      }
    })

    // on applique les changements a l'url
    changeURLParameter('facette', newquery)

    // on ajuste les liens vers les formulaires d'export
    $('.export-links a').each(
      function() {
        const link = $(this).attr('href')
        const queryexists = new RegExp('&query=' + '([^&;]+?)(&|#|;|$)').exec(link) || null
        if (queryexists == null) {
          $(this).attr('href', link + ((newquery !== '') ? `&query=${newquery}` : ''))
        } else {
          const queryinit = $('#queryinit').val()
          if (queryinit) { newquery = `${queryinit}|${newquery}` }
          $(this).attr(
            'href',
            link.replace(new RegExp('&query=' + '([^&;]+?)(&|#|;|$)'), ((newquery !== '') ? `&query=${newquery}` : ''))
          )
        }
      }
    )

    // au moins un filtre à actionner
    let tabres = []
    if (tabfilters.length > 0) {
      // un premier résultat pour le tableau
      tabres = tabfilters[0].toArray()

      // pour chaque boite de filtre, on fait l'intersection avec la suivante
      $.each(tabfilters, (index, tab) => {
        tabres = tabres.filter((n) => tab.toArray().indexOf(n) != -1)
      })
      $('body').trigger('updatefilters', [tabres])
      e.data.$entries.hide().filter(tabres).show()
      e.data.$entries.parent('.bazar-marker').hide()
      e.data.$entries.filter(tabres).parent('.bazar-marker').show()
    } else {
      // pas de filtres: on affiche tout les résultats
      e.data.$entries.show()
      e.data.$entries.parent('.bazar-marker').show()
    }

    // on compte les résultats visibles
    const nbresults = e.data.$entries.filter(':visible').length
    e.data.$nbresults.html(nbresults)
    if (nbresults > 1) {
      e.data.$resultlabel.hide()
      e.data.$resultslabel.show()
    } else {
      e.data.$resultlabel.show()
      e.data.$resultslabel.hide()
    }

    $('body').trigger('updatedfilters', (!tabres.length) ? [] : [tabres])
  }

  // process changes on visible entries according to filters
  $('.facette-container:not(.dynamic)').each(function() {
    const $container = $(this)
    const $filters = $('.filter-checkbox', $container)
    const data = {
      $nbresults: $('.nb-results', $container),
      $filterboxes: $('.filter-box', $container),
      $entries: $('.bazar-entry', $container),
      $resultlabel: $('.result-label', $container),
      $resultslabel: $('.results-label', $container)
    }
    $filters.on('click', data, updateFilters)
    jQuery(window).ready((e) => {
      e.data = data
      updateFilters(e)
    })
  })

  // gestion de l'historique : on reapplique les filtres
  window.onpopstate = function(e) {
    if (e.state && e.state.filter) {
      $('.facette-container').each(function() {
        const $this = $(this)
        $(this).find('input:checkbox').prop('checked', false)
        const urlparamfacette = getURLParameter('facette')
        const tabfacette = urlparamfacette.split('|')
        for (let i = 0; i < tabfacette.length; i++) {
          const tabfilter = tabfacette[i].split('=')
          if (tabfilter[1] !== '') {
            tabvalues = tabfilter[1].split(',')
            for (let j = 0; j < tabvalues.length; j++) {
              $(`#${tabfilter[0]}${tabvalues[j]}`).prop('checked', true)
            }
          }
        }

        const $container = $(this)
        const $filters = $('.filter-checkbox', $container)
        const data = {
          $nbresults: $('.nb-results', $container),
          $filterboxes: $('.filter-box', $container),
          $entries: $('.bazar-entry', $container),
          $resultlabel: $('.result-label', $container),
          $resultslabel: $('.results-label', $container)
        }
        e.data = data
        updateFilters(e)
      })
    }
  }

  // Tags
  // bidouille pour les typeahead des champs tags
  $('.bootstrap-tagsinput input').on('keypress', function() {
    $(this).attr('size', $(this).val().length + 2)
  })

  $('.bootstrap-tagsinput').on('change', function() {
    $(this).parent().find('.yeswiki-input-entries, .yeswiki-input-pagetag').each(function() {
      $(this).tagsinput('input').val('')
    })
  })

  $.extend($.fn.typeahead.Constructor.prototype, { val() {} })

  // on envoie la valeur au submit
  $('#formulaire').on('submit', function() {
    $(this).find('.yeswiki-input-entries, .yeswiki-input-pagetag').each(function() {
      $(this).tagsinput('add', $(this).tagsinput('input').val())
    })
  })

  const bazarList = []
  $('.facette-container:not(.dynamic) .filter-bazar').on('keyup', function(e) {
    const target = $(this).data('target')
    let searchstring = $(this).val()
    if (searchstring) {
      searchstring = searchstring.toLowerCase()
    }
    if (bazarList[target] === undefined) {
      bazarList[target] = []
      $(`#${target} .bazar-entry`).each(function() {
        bazarList[target][$(this).data('id_fiche')] = $(this).find(':visible').text().toLowerCase()
      })
    }
    $(`#${target} .bazar-entry`).hide()
    $(`#${target} .bazar-entry`).filter(function(i) {
      return bazarList[target][$(this).data('id_fiche')].indexOf(searchstring) > -1
    }).show()
    const nbresults = $(`#${target} .bazar-entry:visible`).length
    $(this).parents('.facette-container').find('.nb-results').html(nbresults)
    if (nbresults > 1) {
      $(this).parents('.facette-container').find('.result-label').hide()
      $(this).parents('.facette-container').find('.results-label').show()
    } else {
      $(this).parents('.facette-container').find('.result-label').show()
      $(this).parents('.facette-container').find('.results-label').hide()
    }
  })

  // gestion du bouton de réinitialisation des filtres
  $('.facette-container:not(.dynamic) .filters .reset-filters').on('click', () => {
    $('.facette-container:not(.dynamic) .filters input.filter-checkbox:checked').click()
  })
})

function exportTableToCSV(filename, selector = 'table tr') {
  const csv = []
  const rows = document.querySelectorAll(selector)

  for (let i = 0; i < rows.length; i++) {
    const row = []; const
      cols = rows[i].querySelectorAll('td, th')

    for (let j = 0; j < cols.length; j++) row.push(cols[j].innerText)

    csv.push(row.join(','))
  }

  // Download CSV file
  downloadCSV(csv.join('\n'), filename)
}

function downloadCSV(csv, filename) {
  let csvFile
  let downloadLink

  // CSV file
  csvFile = new Blob([csv], { type: 'text/csv' })
  // Download link
  downloadLink = document.createElement('a')
  // File name
  downloadLink.download = filename
  // Create a link to the file
  downloadLink.href = window.URL.createObjectURL(csvFile)
  // Hide download link
  downloadLink.style.display = 'none'
  // Add the link to DOM
  document.body.appendChild(downloadLink)
  // Click download link
  downloadLink.click()
}

function removeCSVCrochet(str) {
  let res = str.replace(/&lt;/gm, '<')
  res = res.replace(/&gt;/gm, '>')
  return res
}

// range input
$(document).ready(() => {
  const rangeInputs = document.querySelectorAll('.range-wrap input[type="range"]')
  function handleInputChange(e) {
    const { target } = e
    const { min } = target
    const { max } = target
    const val = target.value
    target.style.backgroundSize = `${(val - min) * 100 / (max - min)}% 100%`
    $(target).siblings('output').val(val)
  }

  rangeInputs.forEach((input) => {
    input.addEventListener('input', handleInputChange)
  })
})
