/** javascript for bazar (ticket 16: vanilla JS — no jQuery, no Bootstrap plugins). */

function isVisible(el) {
  return !!el && el.offsetParent !== null
}

ywInitEach('body', () => {
  ywInitEach('.titre_accordeon', (title) => {
    title.addEventListener('click', () => {
      const pane = title.nextElementSibling
      const isPane = pane && pane.matches('div.pane')
      if (title.classList.contains('current')) {
        title.classList.remove('current')
        if (isPane) pane.style.display = 'none'
      } else {
        title.classList.add('current')
        if (isPane) pane.style.display = ''
      }
    })
  })

  ywInitEach('input[name=antispam]', (inputParam) => {
    const input = inputParam
    input.value = '1'
  })

  ywInitEach('#formulaire, #map, #calendar, .accordion', (el) => {
    el.addEventListener('dblclick', (e) => {
      e.preventDefault()
      e.stopPropagation()
    })
  })

  ywInitEach('.select-allday', (select) => {
    select.addEventListener('change', () => {
      const timeBlock = select.parentElement
        ? select.parentElement.nextElementSibling
        : null
      if (!timeBlock || !timeBlock.classList.contains('select-time')) return
      if (select.value === '0') {
        timeBlock.classList.remove('hide')
      } else if (select.value === '1') {
        timeBlock.classList.add('hide')
      }
    })
  })

  function remainingCounterFor(textarea) {
    const group = textarea.closest('.yw-form-group')
    return group ? group.querySelector('.charsRemaining') : null
  }

  ywInitEach('textarea[maxlength]', (textareaParam) => {
    const textarea = textareaParam
    const max = parseInt(textarea.getAttribute('maxlength'), 10)
    if (!max) return
    const counter = remainingCounterFor(textarea)

    if (textarea.classList.contains('aceditor-textarea')) {
      const aceId = `aceditor-${textarea.id}`
      const { editor } = window[aceId]
      if (counter) counter.textContent = max - editor.getValue().length
      if (editor.getValue().length > max) {
        editor.setValue(editor.getValue().substr(0, max))
      }
      editor.on('input', () => {
        const { length } = editor.getValue()
        if (length > max) {
          editor.setValue(editor.getValue().substr(0, max))
        }
        if (counter) counter.textContent = max - editor.getValue().length
      })
    } else if (!textarea.classList.contains('ace_text-input')) {
      if (counter) counter.textContent = max - textarea.value.length
      if (textarea.value.length > max) {
        textarea.value = textarea.value.substr(0, max)
      }
      textarea.addEventListener('keyup', () => {
        if (textarea.value.length > max) {
          textarea.value = textarea.value.substr(0, max)
        }
        if (counter) counter.textContent = max - textarea.value.length
      })
    }
  })

  const enterTrapSelector =
    'form#formulaire .yw-form-group' + ' input.yw-input[type=text]'
  document.querySelectorAll(enterTrapSelector).forEach((item) => {
    item.addEventListener(
      'keydown',
      (event) => {
        if (event.key === 'Enter') {
          event.preventDefault()
          event.stopPropagation()
        }
      },
      true,
    )
  })

  const requirementHelper = {
    requiredInputs: [],
    textInputsWithPattern: [],
    error: -1,
    errorMessage: '',
    errorPattern: -1,
    errorMessagePattern: '',
    filterVisibleInputs(key = 'requiredInputs') {
      this[key] = this[key].filter((input) => {
        let checked = input
        if (
          (input.tagName === 'TEXTAREA' &&
            (input.classList.contains('aceditor-textarea') ||
              input.classList.contains('vditor-wiki') ||
              input.classList.contains('vditor-html'))) ||
          input.closest('[data-yw-tag-input]')
        ) {
          checked = input.parentElement
        }
        if (isVisible(checked)) return true
        let el = checked
        while (el && el !== document.body && !isVisible(el)) {
          const style = window.getComputedStyle(el)
          if (
            style.display === 'none' &&
            el.getAttribute('role') !== 'tabpanel'
          ) {
            return false
          }
          el = el.parentElement
        }
        return true
      })
    },
    getInputType(input) {
      if (input.classList.contains('bazar-date')) {
        return 'date'
      }
      if (input.classList.contains('chk_required')) {
        return 'checkbox'
      }
      if (input.classList.contains('geocode-input')) {
        return 'geocode'
      }
      if (input.classList.contains('radio_required')) {
        return 'radio'
      }
      if (input.closest('[data-yw-tag-input]')) {
        return 'tags'
      }
      if (input.getAttribute('type') === 'email') {
        return 'email'
      }
      if (input.getAttribute('type') === 'url') {
        return 'url'
      }
      if (input.getAttribute('type') === 'range') {
        return 'range'
      }
      if (input.tagName === 'SELECT') {
        return 'select'
      }
      if (input.tagName === 'TEXTAREA') {
        if (input.classList.contains('aceditor-textarea')) {
          return 'wikitextarea'
        }
        return 'textarea'
      }
      return 'default'
    },
    updateError(index) {
      if (this.error === -1) {
        this.error = index
      }
    },
    updateErrorMessage(message) {
      if (this.error === -1) {
        this.errorMessage = message
      }
    },
    dateChecking(input) {
      if (input.value === '') {
        this.updateErrorMessage(_t('BAZ_FORM_REQUIRED_FIELD'))
        return false
      }
      return true
    },
    rangeChecking(input) {
      if (input.value === input.dataset.default) {
        this.updateErrorMessage(_t('BAZ_FORM_REQUIRED_FIELD'))
        return false
      }
      return true
    },
    emailChecking(input) {
      /* eslint-disable no-useless-escape -- a block, not `-next-line`: the formatter is
         free to move this regex onto a line of its own, and a next-line directive then
         lands on the assignment instead of on the pattern it was written for. */
      const reg =
        /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
      /* eslint-enable no-useless-escape */
      if (input.required && !this.defaultChecking(input)) {
        return false
      }
      if (input.value !== '' && reg.test(input.value) === false) {
        this.updateErrorMessage(_t('BAZ_FORM_INVALID_EMAIL'))
        return false
      }
      return true
    },
    urlChecking(input) {
      const reg =
        /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-/]))?/
      if (input.required && !this.defaultChecking(input)) {
        return false
      }
      if (input.value !== '' && reg.test(input.value) === false) {
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
      const value = window[`aceditor-${input.id}`].editor.getValue()
      if (value.length === 0 || value === '') {
        this.updateErrorMessage(_t('BAZ_FORM_REQUIRED_FIELD'))
        input.parentElement.classList.add('invalid')
        return false
      }
      input.parentElement.classList.remove('invalid')
      return true
    },
    checkboxChecking(input) {
      const nbelems = input.querySelectorAll('input:checked')
      const parentToInvalid = input.closest('.yw-form-group.input-checkbox')
      if (nbelems.length === 0) {
        this.updateErrorMessage(_t('BAZ_FORM_EMPTY_CHECKBOX'))
        if (parentToInvalid) parentToInvalid.classList.add('invalid')
        return false
      }
      if (parentToInvalid) parentToInvalid.classList.remove('invalid')
      return true
    },
    radioChecking(input) {
      const nbelems = input.querySelectorAll('input:checked')
      const parentToInvalid = input.closest('.yw-form-group.input-radio')
      if (nbelems.length === 0) {
        this.updateErrorMessage(_t('BAZ_FORM_EMPTY_RADIO'))
        if (parentToInvalid) parentToInvalid.classList.add('invalid')
        return false
      }
      if (parentToInvalid) parentToInvalid.classList.remove('invalid')
      return true
    },
    tagsChecking(input) {
      const widget = input.closest('[data-yw-tag-input]')
      const nbelems = widget
        ? widget.querySelectorAll('[data-yw-tag-input-chip]')
        : []
      if (nbelems.length === 0) {
        this.updateErrorMessage(_t('BAZ_FORM_EMPTY_AUTOCOMPLETE'))
        if (widget) widget.classList.add('invalid')
        return false
      }
      widget.classList.remove('invalid')
      return true
    },
    geocodeChecking(input) {
      const latitude = input.querySelector('.yw-latitude-input')
      const longitude = input.querySelector('.yw-longitude-input')
      const geometries = input.querySelector('.yw-geometries-input')
      if (
        (!latitude || latitude.value === '') &&
        (!longitude || longitude.value === '') &&
        (!geometries || geometries.value === '')
      ) {
        this.updateErrorMessage(_t('BAZ_FORM_EMPTY_GEOLOC'))
        return false
      }
      return true
    },
    defaultChecking(input) {
      if (input.value.length === 0 || input.value === '') {
        this.updateErrorMessage(_t('BAZ_FORM_REQUIRED_FIELD'))
        return false
      }
      return true
    },
    checkInput(input, saveError, index) {
      const inputType = this.getInputType(input)
      if (typeof this[`${inputType}Checking`] !== 'function') {
        input.classList.add('invalid')
        this.updateErrorMessage(
          `Not possible to check field : unknown function requirementHelper.${inputType}Checking() !`,
        )
        if (saveError) {
          this.updateError(index)
        }
      } else if (!this[`${inputType}Checking`](input)) {
        input.classList.add('invalid')
        if (saveError) {
          this.updateError(index)
        }
      } else {
        input.classList.remove('invalid')
      }
    },
    checkPattern(input, index) {
      if (input && input.value.length > 0 && !input.checkValidity()) {
        if (this.errorPattern === -1) {
          this.errorMessagePattern = _t('BAZ_FORM_INVALID_TEXT')
          this.errorPattern = index
        }
      }
    },
    checkInputs() {
      this.requiredInputs.forEach((input, index) => {
        this.checkInput(input, true, index)
      })
      this.textInputsWithPattern.forEach((input, index) => {
        this.checkPattern(input, index)
      })
    },
    displayErrorMessage() {
      alert(this.errorMessage)
    },
    scrollToFirstinputInError(type = 'error') {
      const error = this[type] ?? -1
      if (error > -1) {
        let input = this.requiredInputs[error]
        if (!isVisible(input)) {
          let panel = input.parentElement
          while (
            panel &&
            panel !== document.body &&
            isVisible(panel) === false &&
            (!panel.parentElement || !isVisible(panel.parentElement))
          ) {
            panel = panel.parentElement
          }
          const pane = input.closest('[role=tabpanel]')
          if (pane) {
            const tabLink = document.querySelector(
              `a[href="#${pane.id}"][role=tab]`,
            )
            if (tabLink) tabLink.click()
          }
          if (!isVisible(input)) {
            let closestVisible = input.parentElement
            while (closestVisible && !isVisible(closestVisible)) {
              closestVisible = closestVisible.parentElement
            }
            if (closestVisible) input = closestVisible
          }
        }
        input.scrollIntoView({ behavior: 'smooth', block: 'center' })
      }
    },
    initTextInputsWithPattern(form) {
      this.textInputsWithPattern = Array.from(
        form.querySelectorAll('input[type=text][pattern]'),
      )
      this.errorPattern = -1
    },
    initRequiredInputs(form) {
      this.requiredInputs = Array.from(
        form.querySelectorAll(
          'input[required],' +
            'select[required],' +
            'textarea[required],' +
            ':not(.prev-holder) input[type=email],' +
            ':not(.prev-holder) input[type=url],' +
            '.chk_required,' +
            '.radio_required,' +
            '.geocode-input.required',
        ),
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
      input.addEventListener('keypress', (event) => {
        reqChecking.runWhenUpdated(event.target, reqChecking)
      })
      input.addEventListener('change', (event) => {
        reqChecking.runWhenUpdated(event.target, reqChecking)
      })
    },
    wikitextareaInitlistener(input) {
      const reqChecking = this
      input.addEventListener('change', () => {
        reqChecking.runWhenUpdated(input, reqChecking)
      })
    },
    checkboxInitlistener(input) {
      const reqChecking = this
      input.querySelectorAll('input[type=checkbox]').forEach((checkbox) => {
        checkbox.addEventListener('change', (event) => {
          reqChecking.runWhenUpdated(
            event.target.closest('.chk_required'),
            reqChecking,
          )
        })
      })
    },
    radioInitlistener(input) {
      const reqChecking = this
      input.querySelectorAll('input[type=radio]').forEach((radio) => {
        radio.addEventListener('change', (event) => {
          reqChecking.runWhenUpdated(
            event.target.closest('.radio_required'),
            reqChecking,
          )
        })
      })
    },
    initListeners() {
      const form = document.getElementById('formulaire')
      if (!form) return
      this.initRequiredInputs(form)
      this.requiredInputs.forEach((input) => {
        const inputType = this.getInputType(input)
        if (['default', 'select', 'textarea', 'tags'].indexOf(inputType) > -1) {
          this.inputInitlistener(input)
        } else if (
          ['wikitextarea', 'checkbox', 'radio'].indexOf(inputType) > -1
        ) {
          this[`${inputType}Initlistener`](input)
        }
      })
    },
  }

  requirementHelper.initListeners()
  const formulaire = document.getElementById('formulaire')
  if (formulaire) {
    formulaire.addEventListener('submit', (e) => {
      formulaire.classList.add('submitted')
      try {
        if (requirementHelper.run(formulaire)) {
          setTimeout(() => {
            formulaire
              .querySelectorAll('.form-actions button[type=submit]')
              .forEach((button) => {
                button.setAttribute('disabled', 'disabled')
                button.classList.add('submit-disabled')
                button.setAttribute('title', _t('BAZ_SAVING'))
                setTimeout(() => {
                  button.removeAttribute('disabled')
                }, 10000)
              })
          }, 0)
          return
        }
      } catch (error) {
        console.warn(error.message)
      }
      e.preventDefault()
    })

    formulaire.removeAttribute('onsubmit')
  }

  const startDate = document.querySelector(
    '#formulaire #bf_date_debut_evenement',
  )
  const endDate = document.querySelector('#formulaire #bf_date_fin_evenement')
  if (startDate && endDate) {
    const startAllDay = document.querySelector(
      'select[name="bf_date_debut_evenement_allday"]',
    )
    const endAllDay = document.querySelector(
      'select[name="bf_date_fin_evenement_allday"]',
    )
    const startHour = document.querySelector(
      'select[name="bf_date_debut_evenement_hour"]',
    )
    const startMin = document.querySelector(
      'select[name="bf_date_debut_evenement_minutes"]',
    )
    const endHour = document.querySelector(
      'select[name="bf_date_fin_evenement_hour"]',
    )
    const endMin = document.querySelector(
      'select[name="bf_date_fin_evenement_minutes"]',
    )

    const hasTimeEnabled = () =>
      startAllDay &&
      endAllDay &&
      startAllDay.value === '0' &&
      endAllDay.value === '0'
    const isSameDay = () =>
      startDate.value && endDate.value && startDate.value === endDate.value
    const getStartMinutes = () =>
      parseInt(startHour.value, 10) * 60 + parseInt(startMin.value, 10)
    const getEndMinutes = () =>
      parseInt(endHour.value, 10) * 60 + parseInt(endMin.value, 10)

    const adjustEndTime = () => {
      let total = getStartMinutes() + 5
      if (total >= 1440) total = 1435
      const h = Math.floor(total / 60)
      const m = Math.round((total % 60) / 5) * 5
      endHour.value = String(h).padStart(2, '0')
      endMin.value = String(m).padStart(2, '0')
    }

    const adjustStartTime = () => {
      let total = getEndMinutes() - 5
      if (total < 0) total = 0
      const h = Math.floor(total / 60)
      const m = Math.round((total % 60) / 5) * 5
      startHour.value = String(h).padStart(2, '0')
      startMin.value = String(m).padStart(2, '0')
    }

    const checkTimeConstraint = (changed) => {
      if (!isSameDay() || !hasTimeEnabled()) return
      if (getStartMinutes() >= getEndMinutes()) {
        if (changed === 'start') adjustEndTime()
        else adjustStartTime()
      }
    }

    startDate.addEventListener('change', () => {
      if (startDate.value) {
        endDate.min = startDate.value
        checkTimeConstraint('start')
      }
    })
    endDate.addEventListener('change', () => {
      if (endDate.value) {
        startDate.max = endDate.value
        checkTimeConstraint('end')
      }
    })
    if (startDate.value) endDate.min = startDate.value
    if (endDate.value) startDate.max = endDate.value

    const timeSelects = [startHour, startMin, startAllDay]
    timeSelects.forEach((select) => {
      if (select)
        select.addEventListener('change', () => checkTimeConstraint('start'))
    })
    const endSelects = [endHour, endMin, endAllDay]
    endSelects.forEach((select) => {
      if (select)
        select.addEventListener('change', () => checkTimeConstraint('end'))
    })
  }

  document.querySelectorAll('.bazar-entry').forEach((entry, i) => {
    entry.querySelectorAll('[data-toggle="tab"]').forEach((link) => {
      link.setAttribute('href', `${link.getAttribute('href')}-${i}`)
    })
    entry.querySelectorAll('.yw-tabs__pane, .tab-pane').forEach((paneParam) => {
      const pane = paneParam
      pane.id = `${pane.id}-${i}`
    })
  })

  ywInitEach('.selectall', (selectAll) => {
    selectAll.addEventListener('click', () => {
      let targets
      if (selectAll.dataset.target) {
        targets = document.querySelectorAll(selectAll.dataset.target)
      } else {
        const controls = selectAll.closest('.controls')
        targets = controls ? controls.querySelectorAll('.yeswiki-checkbox') : []
      }
      targets.forEach((targetParam) => {
        const target = targetParam
        target
          .querySelectorAll('input[type=checkbox]')
          .forEach((checkboxParam) => {
            const checkbox = checkboxParam
            checkbox.checked = selectAll.checked
          })
        if (target.matches('input[type=checkbox]')) {
          target.checked = selectAll.checked
        }
      })
    })
  })

  function show(el) {
    el.style.display = ''
  }
  function hideEl(el) {
    el.style.display = 'none'
  }

  const bazarList = []
  ywInitEach('.facette-container:not(.dynamic) .filter-bazar', (filter) => {
    filter.addEventListener('keyup', () => {
      const { target } = filter.dataset
      let searchstring = filter.value
      if (searchstring) {
        searchstring = searchstring.toLowerCase()
      }
      const containerEl = document.getElementById(target)
      if (!containerEl) return
      if (bazarList[target] === undefined) {
        bazarList[target] = []
        containerEl.querySelectorAll('.bazar-entry').forEach((entry) => {
          bazarList[target][entry.dataset.tag] = entry.textContent.toLowerCase()
        })
      }
      let nbresults = 0
      containerEl.querySelectorAll('.bazar-entry').forEach((entry) => {
        const text = bazarList[target][entry.dataset.tag] || ''
        const matches = text.indexOf(searchstring) > -1
        if (matches) {
          show(entry)
          nbresults += 1
        } else {
          hideEl(entry)
        }
      })
      const facetteContainer = filter.closest('.facette-container')
      if (!facetteContainer) return
      facetteContainer.querySelectorAll('.nb-results').forEach((elParam) => {
        const el = elParam
        el.textContent = nbresults
      })
    })
  })
})

export function downloadCSV(csv, filename) {
  const csvFile = new Blob([csv], { type: 'text/csv' })
  const downloadLink = document.createElement('a')
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

ywInitEach('.range-wrap', (wrap) => {
  const rangeInputs = wrap.querySelectorAll('input[type="range"]')
  function handleInputChange(e) {
    const { target } = e
    const { min, max } = target
    const val = target.value
    target.style.backgroundSize = `${((val - min) * 100) / (max - min)}% 100%`
    const output = target.parentElement
      ? target.parentElement.querySelector('output')
      : null
    if (output) output.value = val
  }

  rangeInputs.forEach((input) => {
    input.addEventListener('input', handleInputChange)
  })
})
