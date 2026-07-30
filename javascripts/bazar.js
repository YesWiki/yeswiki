/**
 *
 * javascript for bazar (ticket 16: vanilla JS — no jQuery, no Bootstrap plugins)
 *
 * */

import { updateHash } from './url.js'
import { parseCondition } from './search.js'

let gSavedHash

function isVisible(el) {
  return !!el && el.offsetParent !== null
}

document.addEventListener('DOMContentLoaded', () => {
  gSavedHash = decodeURIComponent(document.location.hash.substring(1))

  // accordeon pour bazarliste
  document.querySelectorAll('.titre_accordeon').forEach((title) => {
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

  // antispam javascript
  document.querySelectorAll('input[name=antispam]').forEach((inputParam) => {
    const input = inputParam
    input.value = '1'
  })

  // The old Google-Maps carto block (#markers/arrMarkers/initialize) was dead
  // code — nothing renders that markup anymore — and has been removed.
  // .tooltip_aide helper icons are sprite <svg>s carrying data-yw-tooltip: pure CSS, no JS.

  // on enleve la fonction doubleclic dans le cas d'une page contenant bazar
  document.querySelectorAll('#formulaire, #map, #calendar, .accordion').forEach((el) => {
    el.addEventListener('dblclick', (e) => {
      e.preventDefault()
      e.stopPropagation()
    })
  })

  // The legacy inline conditional-display system (handleConditionnalListChoice &
  // friends) is gone: ConditionsChecking (javascripts/inputs/conditions-checking.js)
  // is the only system now, as the old code's own TODO planned.

  // choix de l'heure pour une date
  document.querySelectorAll('.select-allday').forEach((select) => {
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

  //= ===========longueur maximale d'un champ textarea
  function remainingCounterFor(textarea) {
    const group = textarea.closest('.yw-form-group')
    return group ? group.querySelector('.charsRemaining') : null
  }

  document.querySelectorAll('textarea[maxlength]').forEach((textareaParam) => {
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

  // éviter la validation du formulaire en pressant la touche Entrée
  const enterTrapSelector = 'form#formulaire .yw-form-group'
    + ' input.yw-input[type=text]'
  document.querySelectorAll(enterTrapSelector).forEach((item) => {
    item.addEventListener(
      'keydown',
      (event) => {
        if (event.key === 'Enter') {
          event.preventDefault()
          event.stopPropagation()
        }
      },
      true
    )
  })

  //= ===========validation formulaire============================

  // validation formulaire de saisie
  const requirementHelper = {
    requiredInputs: [],
    textInputsWithPattern: [],
    error: -1, // error contain the index of the first error (-1 = no error)
    errorMessage: '',
    errorPattern: -1,
    errorMessagePattern: '',
    // an input hidden only because it sits in a non-active tab pane still counts
    filterVisibleInputs(key = 'requiredInputs') {
      this[key] = (this[key]).filter((input) => {
        let checked = input
        if (
          (input.tagName === 'TEXTAREA'
            && (input.classList.contains('aceditor-textarea')
              || input.classList.contains('vditor-html')))
          || input.closest('[data-yw-tag-input]')
        ) {
          checked = input.parentElement
        }
        if (isVisible(checked)) return true
        let el = checked
        while (el && el !== document.body && !isVisible(el)) {
          const style = window.getComputedStyle(el)
          if (style.display === 'none' && el.getAttribute('role') !== 'tabpanel') {
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
        // vditor-html fields are validated like plain textareas: the underlying
        // <textarea>'s value is kept in sync (javascripts/vditor-textarea.js)
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
      // eslint-disable-next-line max-len, no-useless-escape
      const reg = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/ // regex that works for 99,99%, following RFC 5322
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
      const reg = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-/]))?/
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
      const nbelems = widget ? widget.querySelectorAll('[data-yw-tag-input-chip]') : []
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
        (!latitude || latitude.value === '')
        && (!longitude || longitude.value === '')
        && (!geometries || geometries.value === '')
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
          `Not possible to check field : unknown function requirementHelper.${inputType}Checking() !`
        )
        if (saveError) {
          this.updateError(index)
        }
      } else if (!(this[`${inputType}Checking`](input))) {
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
          // the input may live in a non-active tab: activate that tab first
          let panel = input.parentElement
          while (panel && panel !== document.body && isVisible(panel) === false
            && (!panel.parentElement || !isVisible(panel.parentElement))) {
            panel = panel.parentElement
          }
          const pane = input.closest('[role=tabpanel]')
          if (pane) {
            const tabLink = document.querySelector(`a[href="#${pane.id}"][role=tab]`)
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
      this.textInputsWithPattern = Array.from(form.querySelectorAll('input[type=text][pattern]'))
      this.errorPattern = -1
    },
    initRequiredInputs(form) {
      this.requiredInputs = Array.from(form.querySelectorAll(
        'input[required],'
        + 'select[required],'
        + 'textarea[required],'
        + ':not(.prev-holder) input[type=email],'
        + ':not(.prev-holder) input[type=url],'
        + '.chk_required,'
        + '.radio_required,'
        + '.geocode-input.required'
      ))
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
          reqChecking.runWhenUpdated(event.target.closest('.chk_required'), reqChecking)
        })
      })
    },
    radioInitlistener(input) {
      const reqChecking = this
      input.querySelectorAll('input[type=radio]').forEach((radio) => {
        radio.addEventListener('change', (event) => {
          reqChecking.runWhenUpdated(event.target.closest('.radio_required'), reqChecking)
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
        } else if (['wikitextarea', 'checkbox', 'radio'].indexOf(inputType) > -1) {
          this[`${inputType}Initlistener`](input)
        }
      })
    }
  }

  requirementHelper.initListeners()
  const formulaire = document.getElementById('formulaire')
  if (formulaire) {
    formulaire.addEventListener('submit', (e) => {
      formulaire.classList.add('submitted')
      try {
        if (requirementHelper.run(formulaire)) {
          // formulaire validé, on soumet le formulaire mais juste avant on change
          // le comportement du bouton pour éviter les validations multiples
          formulaire.querySelectorAll('.form-actions button[type=submit]').forEach((button) => {
            button.setAttribute('disabled', 'disabled')
            button.classList.add('submit-disabled')
            button.setAttribute('title', _t('BAZ_SAVING'))
            setTimeout(() => {
              // on réactive le bouton au bout de 10s juste pour permettre de
              // forcer une nouvelle validation si jamais ça a planté
              button.removeAttribute('disabled')
            }, 10000)
          })
          return
        }
      } catch (error) {
        console.warn(error.message)
      }
      e.preventDefault()
    })

    // bidouille PEAR form
    formulaire.removeAttribute('onsubmit')
  }

  // dates : les <input type="date"> sont natifs maintenant (plus de datepicker) ;
  // on garde la contrainte début/fin via les attributs min/max natifs
  const startDate = document.querySelector('#formulaire #bf_date_debut_evenement')
  const endDate = document.querySelector('#formulaire #bf_date_fin_evenement')
  if (startDate && endDate) {
    const startAllDay = document.querySelector('select[name="bf_date_debut_evenement_allday"]')
    const endAllDay = document.querySelector('select[name="bf_date_fin_evenement_allday"]')
    const startHour = document.querySelector('select[name="bf_date_debut_evenement_hour"]')
    const startMin = document.querySelector('select[name="bf_date_debut_evenement_minutes"]')
    const endHour = document.querySelector('select[name="bf_date_fin_evenement_hour"]')
    const endMin = document.querySelector('select[name="bf_date_fin_evenement_minutes"]')

    const hasTimeEnabled = () => startAllDay && endAllDay
      && startAllDay.value === '0' && endAllDay.value === '0'
    const isSameDay = () => startDate.value && endDate.value
      && startDate.value === endDate.value
    const getStartMinutes = () => parseInt(startHour.value, 10) * 60 + parseInt(startMin.value, 10)
    const getEndMinutes = () => parseInt(endHour.value, 10) * 60 + parseInt(endMin.value, 10)

    // sélectionne 5 minutes après l'heure de début
    const adjustEndTime = () => {
      let total = getStartMinutes() + 5
      if (total >= 1440) total = 1435
      const h = Math.floor(total / 60)
      const m = Math.round((total % 60) / 5) * 5
      endHour.value = String(h).padStart(2, '0')
      endMin.value = String(m).padStart(2, '0')
    }

    // sélectionne 5 minutes avant l'heure de fin
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
      if (select) select.addEventListener('change', () => checkTimeConstraint('start'))
    })
    const endSelects = [endHour, endMin, endAllDay]
    endSelects.forEach((select) => {
      if (select) select.addEventListener('change', () => checkTimeConstraint('end'))
    })
  }

  // Onglets
  // hack pour les fiches avec tabulations : on change les id pour qu'ils soient uniques
  document.querySelectorAll('.bazar-entry').forEach((entry, i) => {
    entry.querySelectorAll('[data-toggle="tab"]').forEach((link) => {
      link.setAttribute('href', `${link.getAttribute('href')}-${i}`)
    })
    entry.querySelectorAll('.yw-tabs__pane, .tab-pane').forEach((paneParam) => {
      const pane = paneParam
      pane.id = `${pane.id}-${i}`
    })
  })

  // cocher / decocher tous
  document.querySelectorAll('.selectall').forEach((selectAll) => {
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
        target.querySelectorAll('input[type=checkbox]').forEach((checkboxParam) => {
          const checkbox = checkboxParam
          checkbox.checked = selectAll.checked
        })
        if (target.matches('input[type=checkbox]')) {
          target.checked = selectAll.checked
        }
      })
    })
  })

  // facettes

  // recuperer un parametre donné de l'url
  function getURLParameter(name) {
    const match = new RegExp(`[?|&]${name}=([^&;]+?)(&|#|;|$)`)
      .exec(window.location.search)
    return decodeURIComponent((match ? match[1] : '').replace(/\+/g, '%20')) || null
  }

  // modifier un parametre de l'url pour les modifier dynamiquement
  function changeURLParameter(name, value) {
    const s = window.location.search
    let urlquery
    if (getURLParameter(name) == null) {
      urlquery = s.replace(`&${name}=`, '').replace(`?${name}=`, '')
      if (value !== '') {
        urlquery += s !== '' ? `&${name}=${value}` : `?${name}=${value}`
      }
      window.history.pushState({ filter: true }, null, urlquery)
      // pour les url dans une iframe
      if (window.frameElement && window.frameElement.nodeName === 'IFRAME') {
        const iframeurlquery = `${window.top.location.search
          .replace(`&${name}=`, '')}&${name}=${value}`
        window.top.history.pushState({ filter: true }, null, iframeurlquery)
      }
    } else {
      if (value !== '') {
        urlquery = s !== ''
          ? decodeURIComponent(s).replace(
            new RegExp(`&${name}=([^&;]+?)(&|#|;|$)`),
            `&${name}=${value}`
          )
          : `?${name}=${value}`
      } else {
        urlquery = decodeURIComponent(s).replace(
          new RegExp(`[?|&]${name}=([^&;]+?)(&|#|;|$)`),
          ''
        )
      }
      window.history.pushState({ filter: true }, null, urlquery)
      // pour les url dans une iframe
      if (window.frameElement && window.frameElement.nodeName === 'IFRAME') {
        const iframeurlquery = decodeURIComponent(window.top.location.search).replace(
          new RegExp(`[?|&]${name}=([^&;]+?)(&|#|;|$)`),
          `&${name}=${value}`
        )
        window.top.history.pushState({ filter: true }, null, iframeurlquery)
      }
    }
  }

  function facetteData(container) {
    return {
      nbresults: container.querySelectorAll('.nb-results'),
      filterboxes: container.querySelectorAll('.filter-box'),
      entries: Array.from(container.querySelectorAll('.bazar-entry')),
      geometries: Array.from(container.querySelectorAll('.bazar-entry-geometry')),
      resultlabel: container.querySelectorAll('.result-label'),
      resultslabel: container.querySelectorAll('.results-label')
    }
  }

  function show(el) {
    el.style.display = '' // eslint-disable-line no-param-reassign
  }
  function hideEl(el) {
    el.style.display = 'none' // eslint-disable-line no-param-reassign
  }

  // activer les filtres des facettes
  function updateFilters(data) {
    const tabfilters = []
    let newquery = ''
    // on filtre les resultats par boite de filtre pour faire l'intersection apres
    data.filterboxes.forEach((box) => {
      let select = ''
      let first = true
      box.querySelectorAll('.filter-checkbox:checked').forEach((checkbox) => {
        const name = checkbox.getAttribute('name')
        const val = checkbox.getAttribute('value')
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
        // champs non multiples : exactement la valeur; champs multiples : la
        // valeur en début/fin/milieu de la liste separee par des virgules
        select += `[${attr}~="${val}"],[${attr}$=",${val}"],[${attr}^="${val},"],[${attr}*=",${val},"]`
      })
      if (select !== '') {
        const res = data.entries.filter((entry) => entry.matches(select))
        if (res.length > 0) {
          tabfilters.push(res)
        }
      }
    })

    // on applique les changements a l'url
    changeURLParameter('facette', newquery)

    // au moins un filtre à actionner
    let tabres = []
    if (tabfilters.length > 0) {
      // pour chaque boite de filtre, on fait l'intersection avec la suivante
      tabres = tabfilters.reduce(
        (acc, tab) => acc.filter((entry) => tab.indexOf(entry) !== -1)
      )
      document.body.dispatchEvent(
        new CustomEvent('updatefilters', { detail: { entries: tabres } })
      )
      data.entries.forEach((entry) => {
        const kept = tabres.indexOf(entry) !== -1
        if (kept) show(entry)
        else hideEl(entry)
        const marker = entry.parentElement
        if (marker && marker.classList.contains('bazar-marker')) {
          if (kept) show(marker)
          else hideEl(marker)
        }
      })

      // geometries need the id to be hidden
      const idsToMatch = new Set()
      data.entries.forEach((entry) => {
        if (tabres.indexOf(entry) === -1 && entry.dataset.tag) {
          idsToMatch.add(String(entry.dataset.tag))
        }
      })
      data.geometries.forEach((geometry) => {
        if (idsToMatch.has(String(geometry.dataset.id))) hideEl(geometry)
      })
    } else {
      // pas de filtres: on affiche tout les résultats
      data.entries.forEach(show)
      data.geometries.forEach(show)
      data.entries.forEach((entry) => {
        const marker = entry.parentElement
        if (marker && marker.classList.contains('bazar-marker')) show(marker)
      })
    }
    // on compte les résultats visibles (points et geometries confondus)
    const visibleIds = new Set()
    data.entries.filter(isVisible).forEach((entry) => {
      if (entry.dataset.tag) visibleIds.add(String(entry.dataset.tag))
    })
    data.geometries.filter(isVisible).forEach((geometry) => {
      if (geometry.dataset.id) visibleIds.add(String(geometry.dataset.id))
    })
    const nbresults = visibleIds.size
    data.nbresults.forEach((elParam) => {
      const el = elParam
      el.textContent = nbresults
    })
    if (nbresults > 1) {
      data.resultlabel.forEach(hideEl)
      data.resultslabel.forEach(show)
    } else {
      data.resultlabel.forEach(show)
      data.resultslabel.forEach(hideEl)
    }

    document.body.dispatchEvent(
      new CustomEvent('updatedfilters', { detail: { entries: tabres } })
    )

    const vParam = new URLSearchParams(document.location.search)
    const vKeywords = vParam.get('keywords')
    const vSortField = vParam.get('champ')
    const vSortOrder = vParam.get('ordre')

    const vFacette = getURLParameter('facette')
    const vFilters = []
    if (vFacette) {
      vFacette
        .split('|')
        .map(parseCondition)
        .forEach((pCondition) => {
          vFilters[pCondition.name] = pCondition.values
        })
    }

    updateHash(gSavedHash, vKeywords, vSortField, vSortOrder, vFilters)
  }

  // process changes on visible entries according to filters
  setTimeout(() => {
    document.querySelectorAll('.facette-container:not(.dynamic)').forEach((container) => {
      const data = facetteData(container)
      container.querySelectorAll('.filter-checkbox').forEach((checkbox) => {
        checkbox.addEventListener('click', () => updateFilters(data))
      })
      updateFilters(data)
    })
  }, 500)

  // gestion de l'historique : on reapplique les filtres
  window.onpopstate = (e) => {
    if (e.state && e.state.filter) {
      document.querySelectorAll('.facette-container').forEach((container) => {
        container.querySelectorAll('input[type=checkbox]').forEach((checkboxParam) => {
          const checkbox = checkboxParam
          checkbox.checked = false
        })
        const urlparamfacette = getURLParameter('facette')
        if (urlparamfacette) {
          urlparamfacette.split('|').forEach((facette) => {
            const tabfilter = facette.split('=')
            if (tabfilter[1] !== '') {
              tabfilter[1].split(',').forEach((value) => {
                const checkbox = document.getElementById(`${tabfilter[0]}${value}`)
                if (checkbox) checkbox.checked = true
              })
            }
          })
        }
        updateFilters(facetteData(container))
      })
    }
  }

  // The bootstrap-tagsinput/typeahead glue that used to live here is gone: tag
  // fields are yw-tags-input.js widgets that manage their own input and value.

  const bazarList = []
  document.querySelectorAll('.facette-container:not(.dynamic) .filter-bazar').forEach((filter) => {
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
      facetteContainer.querySelectorAll('.result-label').forEach(
        nbresults > 1 ? hideEl : show
      )
      facetteContainer.querySelectorAll('.results-label').forEach(
        nbresults > 1 ? show : hideEl
      )
    })
  })

  // gestion du bouton de réinitialisation des filtres
  document.querySelectorAll('.facette-container:not(.dynamic) .filters .reset-filters')
    .forEach((reset) => {
      reset.addEventListener('click', () => {
        document.querySelectorAll(
          '.facette-container:not(.dynamic) .filters input.filter-checkbox:checked'
        ).forEach((checkbox) => checkbox.click())
      })
    })
})

export function downloadCSV(csv, filename) {
  // CSV file
  const csvFile = new Blob([csv], { type: 'text/csv' })
  // Download link
  const downloadLink = document.createElement('a')
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

export function removeCSVCrochet(str) {
  let res = str.replace(/&lt;/gm, '<')
  res = res.replace(/&gt;/gm, '>')
  return res
}

// range input
document.addEventListener('DOMContentLoaded', () => {
  const rangeInputs = document.querySelectorAll('.range-wrap input[type="range"]')
  function handleInputChange(e) {
    const { target } = e
    const { min, max } = target
    const val = target.value
    target.style.backgroundSize = `${((val - min) * 100) / (max - min)}% 100%`
    const output = target.parentElement ? target.parentElement.querySelector('output') : null
    if (output) output.value = val
  }

  rangeInputs.forEach((input) => {
    input.addEventListener('input', handleInputChange)
  })
})
