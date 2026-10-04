/** Bazar forms and lists in the browser. */

import { updateHash } from './url.js'
import { parseCondition } from './search.js'

let gSavedHash

$(document).ready(() => {
  gSavedHash = decodeURIComponent(document.location.hash.substring(1))

  $('.titre_accordeon').on('click', function () {
    if ($(this).hasClass('current')) {
      $(this).removeClass('current')
      $(this).next('div.pane').hide()
    } else {
      $(this).addClass('current')
      $(this).next('div.pane').show()
    }
  })

  $(document).on('submit', 'form', function () {
    $(this).find('input[name=antispam]').val('1')
  })

  // carto google
  const divcarto = document.getElementById('map')
  if (divcarto) {
    initialize()
  }

  $('#markers a').on('click', function () {
    const i = $(this).attr('rel')

    for (let x = 0; x < arrInfoWindows.length; x++) {
      arrInfoWindows[x].close()
    }

    arrInfoWindows[i].open(map, arrMarkers[i])
    $('ul.css-tabs li').remove()
    $('fieldset.tab').each(function (i) {
      $(this)
        .parent('div.BAZ_cadre_fiche')
        .prev('ul.css-tabs')
        .append(
          `<li class='liste${i}'><a href="#">${$(this).find('legend:first').hide().html()}</a></li>`,
        )
    })

    $('ul.css-tabs').tabs('fieldset.tab', { onClick() {} })
  })

  $('img.tooltip_aide[title], .bazar-marker').each(function () {
    $(this).tooltip({
      animation: true,
      delay: 0,
      position: 'top',
    })
  })

  $('.bazar-form, #map, #calendar, .accordion').bind('dblclick', (_e) => false)

  function emptyChildren(element) {
    if (typeof ConditionsChecking === 'undefined') {
      $(element).find(':input').val('').removeProp('checked')
    } else {
      ConditionsChecking.emptyChildren(element)
    }
  }

  function handleConditionnalListChoice() {
    const id = $(this).attr('id')
    $(`div[id^='${id}'], div[id^='${id.replace('liste', '')}']`)
      .not(
        `div[id='${id}_${$(this).val()}'], div[id='${id.replace('liste', '')}_${$(this).val()}']`,
      )
      .hide()
      .each(function () {
        emptyChildren(this)
      })
    $(
      `div[id='${id}_${$(this).val()}'], div[id='${id.replace('liste', '')}_${$(this).val()}']`,
    ).show()
  }
  function handleConditionnalRadioChoice() {
    const id = $(this).attr('id')
    const shortId = id.substr(
      0,
      id.length - $(this).val().toString().length - 1,
    )
    $(`div[id^='${shortId}']`)
      .not(`div[id='${id}']`)
      .hide()
      .each(function () {
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
        $(
          `div[id='${m[1]}_${m[2]}']:not(.conditional_inversed_checkbox)`,
        ).show()
        $(`div[id='${m[1]}_${m[2]}'].conditional_inversed_checkbox`)
          .hide()
          .each(function () {
            emptyChildren(this)
          })
      } else {
        $(`div[id='${m[1]}_${m[2]}']:not(.conditional_inversed_checkbox)`)
          .hide()
          .each(function () {
            emptyChildren(this)
          })
        $(`div[id='${m[1]}_${m[2]}'].conditional_inversed_checkbox`).show()
      }
    }
  }

  $("select[id^='liste']")
    .each(handleConditionnalListChoice)
    .change(handleConditionnalListChoice)
  $('input.element_radio')
    .each(handleConditionnalRadioChoice)
    .change(handleConditionnalRadioChoice)
  $(".element_checkbox[id^='checkboxListe']")
    .each(handleConditionnalCheckboxChoice)
    .change(handleConditionnalCheckboxChoice)

  $('.select-allday').change(function () {
    if ($(this).val() === '0') {
      $(this).parent().next('.select-time').removeClass('hide')
    } else if ($(this).val() === '1') {
      $(this).parent().next('.select-time').addClass('hide')
    }
  })

  const $textareas = $("textarea[maxlength!='']")

  $textareas.each(function () {
    const $this = $(this)
    const max = $this.attr('maxlength')
    if ($this.hasClass('aceditor-textarea')) {
      const { length } =
        window[`aceditor-${$this.attr('id')}`].editor.getValue()
      $this
        .parents('.control-group')
        .find('.charsRemaining')
        .text(max - length)
    } else if (!$this.hasClass('ace_text-input')) {
      const { length } = $this.val()
      $this
        .parents('.control-group')
        .find('.charsRemaining')
        .text(max - length)
    }
    if (length > max) {
      if ($this.hasClass('aceditor-textarea')) {
        const aceId = `aceditor-${$this.attr('id')}`
        window[aceId].editor.setValue(
          window[aceId].editor.getValue().substr(0, max),
        )
      } else if (!$this.hasClass('ace_text-input')) {
        $this.val($this.val().substr(0, max))
      }
    }

    if ($this.hasClass('aceditor-textarea')) {
      const aceId = `aceditor-${$this.attr('id')}`
      window[aceId].editor.on('input', () => {
        const $ed = $(this)
        const max = $ed.attr('maxlength')
        const { length } = $ed.val()
        if (length > max) {
          window[aceId].editor.setValue(
            window[aceId].editor.getValue().substr(0, max),
          )
        }
        $this
          .parents('.control-group')
          .find('.charsRemaining')
          .text(max - length)
      })
    } else if (!$this.hasClass('ace_text-input')) {
      $this.on('keyup', function () {
        const $ed = $(this)
        const max = $ed.attr('maxlength')
        const { length } = $ed.val()
        if (length > max) {
          $ed.val($ed.val().substr(0, max))
        }

        $this
          .parents('.control-group')
          .find('.charsRemaining')
          .text(max - length)
      })
    }
  })

  document
    .querySelectorAll(
      'form.bazar-form .control-group.form-group input.form-control[type=text]',
    )
    .forEach((item) => {
      item.addEventListener(
        'keydown',
        (event) => {
          if (event.key === 'Enter') {
            event.preventDefault()
            event.stopPropagation()
            return true
          }
        },
        true,
      )
    })

  $('object').append('<param value="opaque" name="wmode">')
  $('embed').attr('wmode', 'opaque')

  const requirementHelper = {
    requiredInputs: [],
    textInputsWithPattern: [],
    error: -1,
    errorMessage: '',
    errorPattern: -1,
    errorMessagePattern: '',
    filterVisibleInputs(key = 'requiredInputs') {
      this[key] = this[key].filter(function () {
        let inputVisible = $(this).filter(':visible')
        if (
          ($(this).prop('tagName') == 'TEXTAREA' &&
            ($(this).hasClass('aceditor-textarea') ||
              $(this).hasClass('summernote'))) ||
          $(this).siblings('.bootstrap-tagsinput').length > 0
        ) {
          inputVisible = $(this).parent().filter(':visible')
        }
        if (typeof inputVisible !== 'undefined' && inputVisible.length > 0) {
          return true
        }
        const notVisibleParents = $(this).parentsUntil(':visible')
        if (
          typeof notVisibleParents === 'undefined' ||
          notVisibleParents.length == 0
        ) {
          return false
        }
        if (
          $(this)
            .parentsUntil(':visible')
            .filter(function () {
              return (
                $(this).css('display') == 'none' &&
                $(this).attr('role') != 'tabpanel'
              )
            }).length == 0
        ) {
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
        if ($(input).hasClass('aceditor-textarea')) {
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
      const reg =
        /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
      if ($(input).prop('required') && !this.defaultChecking(input)) {
        return false
      }
      if ($(input).val() != '' && reg.test($(input).val()) === false) {
        this.updateErrorMessage(_t('BAZ_FORM_INVALID_EMAIL'))
        return false
      }
      return true
    },
    urlChecking(input) {
      const reg =
        /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-/]))?/
      if ($(input).prop('required') && !this.defaultChecking(input)) {
        return false
      }
      if ($(input).val() != '' && reg.test($(input).val()) === false) {
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
      const value = window[`aceditor-${$(input).attr('id')}`].editor.getValue()
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
        $(input)
          .closest('.form-control.textarea.summernote')
          .addClass('invalid')
        this.updateErrorMessage(_t('BAZ_FORM_REQUIRED_FIELD'))
        return false
      }
      $(input)
        .closest('.form-control.textarea.summernote')
        .removeClass('invalid')
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
      const vLatitude = $(input).find('.yw-latitude-input').val()
      const vLongitude = $(input).find('.yw-longitude-input').val()
      const vGeometries = $(input).find('.yw-geometries-input').val()
      if (vLatitude == '' && vLongitude == '' && vGeometries == '') {
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
        this.updateErrorMessage(
          `Not possible to check field : unknown function requirementHelper.${inputType}Checking() !`,
        )
        if (saveError) {
          this.updateError(index)
        }
      } else if (!this[`${inputType}Checking`](input)) {
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
        let input = this.requiredInputs[error]
        if ($(input).filter(':visible').length == 0) {
          const panel = $(input).parentsUntil(':visible').last()
          if ($(panel).attr('role') == 'tabpanel') {
            $(`a[href="#${$(panel).attr('id')}"][role=tab]`)
              .first()
              .click()
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
        'input[required],' +
          'select[required],' +
          'textarea[required],' +
          ':not(.prev-holder) input[type=email],' +
          ':not(.prev-holder) input[type=url],' +
          '.chk_required,' +
          '.radio_required,' +
          '.geocode-input.required',
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
      const aceditor = $(input)
      aceditor.on('change', (_event) => {
        reqChecking.runWhenUpdated(input, reqChecking)
      })
    },
    checkboxInitlistener(input) {
      const reqChecking = this
      const checkboxes = $(input).find('input[type=checkbox]')
      $(checkboxes).change((event) => {
        reqChecking.runWhenUpdated(
          $(event.target).closest('.chk_required'),
          reqChecking,
        )
      })
    },
    radioInitlistener(input) {
      const reqChecking = this
      const radioButtons = $(input).find('input[type=radio]')
      $(radioButtons).change((event) => {
        reqChecking.runWhenUpdated(
          $(event.target).closest('.radio_required'),
          reqChecking,
        )
      })
    },
    initListeners() {
      this.initRequiredInputs($('.bazar-form'))
      for (let index = 0; index < this.requiredInputs.length; index++) {
        const input = this.requiredInputs[index]
        const inputType = this.getInputType(input)
        if (['default', 'select', 'textarea', 'tags'].indexOf(inputType) > -1) {
          this.inputInitlistener(input)
        } else if (
          ['summernote', 'wikitextarea', 'checkbox', 'radio'].indexOf(
            inputType,
          ) > -1
        ) {
          this[`${inputType}Initlistener`](input)
        }
      }
    },
  }

  requirementHelper.initListeners()
  $('.bazar-form').submit(function (e) {
    $(this).addClass('submitted')

    try {
      if (requirementHelper.run(this)) {
        $(this)
          .find('.form-actions button[type=submit]')
          .each(function () {
            $(this).attr('disabled', true)
            $(this).addClass('submit-disabled')
            $(this).attr('title', _t('BAZ_SAVING'))
            const button = $(this)
            setTimeout(() => {
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

  $('.bazar-form').removeAttr('onsubmit')

  const $dateinputs = $('.bazar-date')

  if ($dateinputs.length > 0) {
    $.fn.datepicker.dates.fr = {
      days: [
        'SUNDAY',
        'MONDAY',
        'TUESDAY',
        'WEDNESDAY',
        'THURSDAY',
        'FRIDAY',
        'SATURDAY',
        'SUNDAY',
      ].map((day) => _t(day)),
      daysShort: [
        'SUNDAY',
        'MONDAY',
        'TUESDAY',
        'WEDNESDAY',
        'THURSDAY',
        'FRIDAY',
        'SATURDAY',
        'SUNDAY',
      ].map((day) => _t(`BAZ_DATESHORT_${day}`)),
      daysMin: [
        'SUNDAY',
        'MONDAY',
        'TUESDAY',
        'WEDNESDAY',
        'THURSDAY',
        'FRIDAY',
        'SATURDAY',
        'SUNDAY',
      ].map((day) => _t(`BAZ_DATEMIN_${day}`)),
      months: [
        'JANUARY',
        'FEBRUARY',
        'MARCH',
        'APRIL',
        'MAY',
        'JUNE',
        'JULY',
        'AUGUST',
        'SEPTEMBER',
        'OCTOBER',
        'NOVEMBER',
        'DECEMBER',
      ].map((month) => _t(month)),
      monthsShort: [
        'JANUARY',
        'FEBRUARY',
        'MARCH',
        'APRIL',
        'MAY',
        'JUNE',
        'JULY',
        'AUGUST',
        'SEPTEMBER',
        'OCTOBER',
        'NOVEMBER',
        'DECEMBER',
      ].map((month) => _t(`BAZ_DATESHORT_${month}`)),
    }
    $dateinputs
      .datepicker({
        format: 'yyyy-mm-dd',
        weekStart: 1,
        autoclose: true,
        language: 'fr',
      })
      .attr('autocomplete', 'off')
  }

  const $startDate = $('.bazar-form #bf_date_debut_evenement')
  const $endDate = $('.bazar-form #bf_date_fin_evenement')
  if ($startDate.length && $endDate.length) {
    const startPicker = $startDate.data('datepicker')
    const endPicker = $endDate.data('datepicker')
    $startDate.on('changeDate', () => {
      if ($startDate.val()) {
        endPicker.setStartDate($startDate.val())
        checkTimeConstraint('start')
      }
    })
    $endDate.on('changeDate', () => {
      if ($endDate.val()) {
        startPicker.setEndDate($endDate.val())
        checkTimeConstraint('end')
      }
    })

    const $startAllDay = $('select[name="bf_date_debut_evenement_allday"]')
    const $endAllDay = $('select[name="bf_date_fin_evenement_allday"]')
    const $startHour = $('select[name="bf_date_debut_evenement_hour"]')
    const $startMin = $('select[name="bf_date_debut_evenement_minutes"]')
    const $endHour = $('select[name="bf_date_fin_evenement_hour"]')
    const $endMin = $('select[name="bf_date_fin_evenement_minutes"]')

    function hasTimeEnabled() {
      return $startAllDay.val() === '0' && $endAllDay.val() === '0'
    }

    function isSameDay() {
      return (
        $startDate.val() &&
        $endDate.val() &&
        $startDate.val() === $endDate.val()
      )
    }

    function getStartMinutes() {
      return parseInt($startHour.val()) * 60 + parseInt($startMin.val())
    }

    function getEndMinutes() {
      return parseInt($endHour.val()) * 60 + parseInt($endMin.val())
    }

    function adjustEndTime() {
      let total = getStartMinutes() + 5
      if (total >= 1440) total = 1435
      const h = Math.floor(total / 60)
      const m = Math.round((total % 60) / 5) * 5
      $endHour.val(String(h).padStart(2, '0'))
      $endMin.val(String(m).padStart(2, '0'))
    }

    function adjustStartTime() {
      let total = getEndMinutes() - 5
      if (total < 0) total = 0
      const h = Math.floor(total / 60)
      const m = Math.round((total % 60) / 5) * 5
      $startHour.val(String(h).padStart(2, '0'))
      $startMin.val(String(m).padStart(2, '0'))
    }

    function checkTimeConstraint(changed) {
      if (!isSameDay() || !hasTimeEnabled()) return
      if (getStartMinutes() >= getEndMinutes()) {
        if (changed === 'start') adjustEndTime()
        else adjustStartTime()
      }
    }

    $startHour.add($startMin).on('change', () => checkTimeConstraint('start'))
    $endHour.add($endMin).on('change', () => checkTimeConstraint('end'))
    $startAllDay
      .add($endAllDay)
      .on('change', () => checkTimeConstraint('start'))
  }

  $('.bazar-entry').each(function (i) {
    $(this)
      .find('[data-toggle="tab"]')
      .each(function () {
        $(this).attr('href', `${$(this).attr('href')}-${i}`)
      })

    $(this)
      .find('.tab-pane')
      .each(function () {
        $(this).attr('id', `${$(this).attr('id')}-${i}`)
      })
  })

  const checkboxselectall = $('.selectall')
  checkboxselectall.click(function (_event) {
    const $this = $(this)
    let target = $this.parents('.controls').find('.yeswiki-checkbox')
    if ($this.data('target')) {
      const $form = $this.closest('form')
      target = ($form.length ? $form : $(document)).find($this.data('target'))
    }

    if (this.checked) {
      target.each(function () {
        $(this).find(':checkbox').prop('checked', true)
        $(this).prop('checked', true)
      })
    } else {
      target.each(function () {
        $(this).find(':checkbox').prop('checked', false)
        $(this).prop('checked', false)
      })
    }
  })

  function getURLParameter(name) {
    return (
      decodeURIComponent(
        (new RegExp(`[?|&]${name}=` + '([^&;]+?)(&|#|;|$)').exec(
          location.search,
        ) || ['', ''])[1].replace(/\+/g, '%20'),
      ) || null
    )
  }

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

      if (window.frameElement && window.frameElement.nodeName == 'IFRAME') {
        var iframeurlquery = `${window.top.location.search.replace(
          `&${name}=`,
          '',
        )}&${name}=${value}`
        window.top.history.pushState({ filter: true }, null, iframeurlquery)
      }
    } else {
      s = location.search
      if (value !== '') {
        if (s !== '') {
          urlquery = decodeURIComponent(s).replace(
            new RegExp(`&${name}=` + '([^&;]+?)(&|#|;|$)'),
            `&${name}=${value}`,
          )
        } else {
          urlquery = `?${name}=${value}`
        }
      } else {
        urlquery = decodeURIComponent(s).replace(
          new RegExp(`[?|&]${name}=` + '([^&;]+?)(&|#|;|$)'),
          '',
        )
      }

      history.pushState({ filter: true }, null, urlquery)

      if (window.frameElement && window.frameElement.nodeName == 'IFRAME') {
        iframeurlquery = decodeURIComponent(window.top.location.search).replace(
          new RegExp(`[?|&]${name}=` + '([^&;]+?)(&|#|;|$)'),
          `&${name}=${value}`,
        )
        window.top.history.pushState({ filter: true }, null, iframeurlquery)
      }

      return location.search
    }
  }

  // entries of a facet container, without entries nested inside another entry
  function topLevelEntries($container) {
    return $('.bazar-entry', $container).filter(function () {
      return (
        $(this).parent().closest('.bazar-entry', $container[0]).length === 0
      )
    })
  }

  // filter boxes of this facet container, not of a nested one
  function ownFilterBoxes($container) {
    return $('.filter-box', $container).filter(function () {
      return $(this).closest('.facette-container').is($container)
    })
  }

  function updateFilters(e) {
    const tabfilters = []
    let i = 0
    let newquery = ''
    let select
    e.data.$filterboxes.each(function () {
      select = ''
      let first = true
      const filterschk = $(this).find('.filter-checkbox:checked')
      $.each(filterschk, (index, checkbox) => {
        const name = $(checkbox).attr('name')
        const val = $(checkbox).attr('value')
        const attr = `data-${name.toLowerCase()}`
        if (first) {
          if (newquery !== '') {
            newquery += '|'
          }
          newquery += `${name}=${val}`
          first = false
        } else {
          newquery += `,${val}`
          select += ','
        }

        select += `[${attr}~="${val}"],[${attr}$=",${val}"],[${attr}^="${val},"],[${attr}*=",${val},"]`
      })
      const res = e.data.$entries.filter(select)
      if (res.length > 0) {
        tabfilters[i] = res
        i += 1
      }
    })

    changeURLParameter('facette', newquery)

    let tabres = []
    if (tabfilters.length > 0) {
      tabres = tabfilters[0].toArray()

      $.each(tabfilters, (index, tab) => {
        tabres = tabres.filter((n) => tab.toArray().indexOf(n) != -1)
      })
      $('body').trigger('updatefilters', [tabres])
      e.data.$entries.hide().filter(tabres).show()
      e.data.$entries.parent('.bazar-marker').hide()
      e.data.$entries.filter(tabres).parent('.bazar-marker').show()

      const $toHide = e.data.$entries.not(tabres)
      const idsToMatch = new Set()

      $toHide.each(function () {
        const id = $(this).attr('data-id_fiche')
        if (id) {
          idsToMatch.add(String(id))
        }
      })

      const matchingGeometries = e.data.$geometries.filter(function () {
        const geoId = $(this).attr('data-id')
        return idsToMatch.has(String(geoId))
      })

      matchingGeometries.hide()
    } else {
      e.data.$entries.show()
      e.data.$geometries.show()
      e.data.$entries.parent('.bazar-marker').show()
    }
    const visibleIds = new Set()
    e.data.$entries.filter(':visible').each(function () {
      const id = $(this).attr('data-id_fiche')
      if (id) visibleIds.add(String(id))
    })
    e.data.$geometries.filter(':visible').each(function () {
      const id = $(this).attr('data-id')
      if (id) visibleIds.add(String(id))
    })
    const nbresults = visibleIds.size
    e.data.$nbresults.html(nbresults)
    if (nbresults > 1) {
      e.data.$resultlabel.hide()
      e.data.$resultslabel.show()
    } else {
      e.data.$resultlabel.show()
      e.data.$resultslabel.hide()
    }

    $('body').trigger('updatedfilters', !tabres.length ? [] : [tabres])

    const vParam = new URLSearchParams(document.location.search)
    const vKeywords = vParam.get('keywords')
    const vSortField = vParam.get('champ')
    const vSortOrder = vParam.get('ordre')

    const vFacette = getURLParameter('facette')
    let vFilters = []
    if (vFacette) {
      getURLParameter('facette')
        .split('|')
        .map(parseCondition)
        .forEach((pCondition) => {
          vFilters[pCondition.name] = pCondition.values
        })
    } else vFilters = []

    updateHash(gSavedHash, vKeywords, vSortField, vSortOrder, vFilters)
  }

  setTimeout(() => {
    $('.facette-container:not(.dynamic)').each(function () {
      const $container = $(this)
      const $filters = $('.filter-checkbox', $container)
      const data = {
        $nbresults: $('.nb-results', $container),
        $filterboxes: ownFilterBoxes($container),
        $entries: topLevelEntries($container),
        $geometries: $('.bazar-entry-geometry', $container),
        $resultlabel: $('.result-label', $container),
        $resultslabel: $('.results-label', $container),
      }
      $filters.on('click', data, updateFilters)
      jQuery(window).ready((e) => {
        e.data = data
        updateFilters(e)
      })
    })
  }, 500)

  window.onpopstate = function (e) {
    if (e.state && e.state.filter) {
      $('.facette-container').each(function () {
        $(this).find('input:checkbox').prop('checked', false)
        const urlparamfacette = getURLParameter('facette')

        const tabfacette = urlparamfacette.split('|')
        for (let i = 0; i < tabfacette.length; i++) {
          const tabfilter = tabfacette[i].split('=')
          if (tabfilter[1] !== '') {
            const tabvalues = tabfilter[1].split(',')
            for (let j = 0; j < tabvalues.length; j++) {
              $(`#${tabfilter[0]}${tabvalues[j]}`).prop('checked', true)
            }
          }
        }

        const $container = $(this)
        const data = {
          $nbresults: $('.nb-results', $container),
          $filterboxes: ownFilterBoxes($container),
          $entries: topLevelEntries($container),
          $geometries: $('.bazar-entry-geometry', $container),
          $resultlabel: $('.result-label', $container),
          $resultslabel: $('.results-label', $container),
        }
        e.data = data
        updateFilters(e)
      })
    }
  }

  $('.bootstrap-tagsinput input').on('keypress', function () {
    $(this).attr('size', $(this).val().length + 2)
  })

  $('.bootstrap-tagsinput').on('change', function () {
    $(this)
      .parent()
      .find('.yeswiki-input-entries, .yeswiki-input-pagetag')
      .each(function () {
        $(this).tagsinput('input').val('')
      })
  })

  $.extend($.fn.typeahead.Constructor.prototype, { val() {} })

  $('.bazar-form').on('submit', function () {
    $(this)
      .find('.yeswiki-input-entries, .yeswiki-input-pagetag')
      .each(function () {
        $(this).tagsinput('add', $(this).tagsinput('input').val())
      })
  })

  const bazarList = []
  $('.facette-container:not(.dynamic) .filter-bazar').on(
    'keyup',
    function (_e) {
      const target = $(this).data('target')
      let searchstring = $(this).val()
      if (searchstring) {
        searchstring = searchstring.toLowerCase()
      }
      const $entries = topLevelEntries($(`#${target}`))
      if (bazarList[target] === undefined) {
        bazarList[target] = []
        $entries.each(function () {
          bazarList[target][$(this).data('id_fiche')] = $(this)
            .find(':visible')
            .text()
            .toLowerCase()
        })
      }
      $entries.hide()
      $entries
        .filter(function (_i) {
          return (
            bazarList[target][$(this).data('id_fiche')].indexOf(searchstring) >
            -1
          )
        })
        .show()
      const nbresults = $entries.filter(':visible').length
      $(this).parents('.facette-container').find('.nb-results').html(nbresults)
      if (nbresults > 1) {
        $(this).parents('.facette-container').find('.result-label').hide()
        $(this).parents('.facette-container').find('.results-label').show()
      } else {
        $(this).parents('.facette-container').find('.result-label').show()
        $(this).parents('.facette-container').find('.results-label').hide()
      }
    },
  )

  $('.facette-container:not(.dynamic) .filters .reset-filters').on(
    'click',
    () => {
      $(
        '.facette-container:not(.dynamic) .filters input.filter-checkbox:checked',
      ).click()
    },
  )
})

function exportTableToCSV(filename, selector = 'table tr') {
  const csv = []
  const rows = document.querySelectorAll(selector)

  for (let i = 0; i < rows.length; i++) {
    const row = []
    const cols = rows[i].querySelectorAll('td, th')

    for (let j = 0; j < cols.length; j++) row.push(cols[j].innerText)

    csv.push(row.join(','))
  }

  downloadCSV(csv.join('\n'), filename)
}

export function downloadCSV(csv, filename) {
  let csvFile
  let downloadLink

  csvFile = new Blob([csv], { type: 'text/csv' })
  downloadLink = document.createElement('a')
  downloadLink.download = filename
  downloadLink.href = window.URL.createObjectURL(csvFile)
  downloadLink.style.display = 'none'
  document.body.appendChild(downloadLink)
  downloadLink.click()
}

export function removeCSVCrochet(str) {
  let res = str.replace(/&lt;/gm, '<')
  res = res.replace(/&gt;/gm, '>')
  return res
}

$(document).ready(() => {
  const rangeInputs = document.querySelectorAll(
    '.range-wrap input[type="range"]',
  )
  function handleInputChange(e) {
    const { target } = e
    const { min } = target
    const { max } = target
    const val = target.value
    target.style.backgroundSize = `${((val - min) * 100) / (max - min)}% 100%`
    $(target).siblings('output').val(val)
  }

  rangeInputs.forEach((input) => {
    input.addEventListener('input', handleInputChange)
  })
})

window.exportTableToCSV = exportTableToCSV
