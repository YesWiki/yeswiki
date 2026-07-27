// conditions-checking.js — conditional display of bazar form blocks driven by
// data-conditionschecking expressions (ticket 16: vanilla JS; tag fields are
// yw-tag-input widgets, tab panes use the yw-tabs__pane markup)
const ConditionsChecking = {
  checkDelay: 500, // delay between 2 wysiwig editor content condition checking
  conditionsCache: [],
  fieldNamesCache: {},
  triggersCache: {},
  tagsDefaultsCache: new WeakMap(),
  boolList: ['false', 'true'],
  operationsList: ['!(', 'not(', 'not (', '(', ')'],
  operationsListIncludInSpaceParenthesis: ['and', 'or'],
  conditionsList: [
    'match', '==', '!=', ' in',
    '|length ==', '|length !=', '|length <', '|length <=', '|length >=', '|length >',
    ' is empty', ' is not empty'
  ],
  isVisible(el) {
    return !!el && el.offsetParent !== null
  },
  fireChange(el) {
    el.dispatchEvent(new Event('change', { bubbles: true }))
  },
  pregQuote(input) {
    return (`${input}`).replace(/[.[\]^(){}!=\\+*?$<>|:]/g, '\\$&')
  },
  updateOperationData(dataParam, rest, condition, element) {
    const data = dataParam
    const newIndex = (rest === undefined || rest === null) ? -1 : condition.length - rest[0].length
    if (Object.keys(data).length === 0 || data.indexOf === undefined) {
      data.indexOf = -1
    }
    if (newIndex > -1 && (newIndex < data.indexOf || data.indexOf < 0)) {
      data.indexOf = newIndex
      data.element = element;
      [, data.fullRest] = rest
    }
  },
  updateObject(data, condition, names) {
    const result = {}
    if (data.indexOf < 0) {
      result[names.current] = condition
      result[names.rest] = ''
      result[names.element] = ''
    } else {
      result[names.current] = condition.substr(0, data.indexOf).trim()
      result[names.element] = data.element
      result[names.rest] = condition.substr(data.indexOf + data.fullRest.length).trim()
    }
    return result
  },
  getFirstOperation(parsingObject) {
    const condition = parsingObject.restOfCondition.trim()
    const data = {}
    this.operationsList.forEach((element) => {
      const rest = condition.match(new RegExp(`(${this.pregQuote(element)})(.*)$`, 'i'))
      this.updateOperationData(data, rest, condition, element)
    })
    this.operationsListIncludInSpaceParenthesis.forEach((element) => {
      const rest = condition.match(new RegExp(`((?<= |\\)|^)${element}(?= |\\)))(.*)$`, 'i'))
      this.updateOperationData(data, rest, condition, element)
    })
    const names = { current: 'currentCondition', rest: 'restOfCondition', element: 'operation' }
    return this.updateObject(data, condition, names)
  },
  addCondition(condition) {
    const conditionLocal = condition.trim()
    const data = {}
    this.conditionsList.forEach((element) => {
      const rest = conditionLocal.match(new RegExp(`(${this.pregQuote(element)})(.*)$`, 'i'))
      this.updateOperationData(data, rest, conditionLocal, element)
    })
    this.boolList.forEach((element) => {
      const rest = conditionLocal.match(new RegExp(`(${this.pregQuote(element)})(.*)$`, 'i'))
      this.updateOperationData(data, rest, conditionLocal, element)
    })
    const names = { current: 'leftPart', rest: 'rightPart', element: 'typeOfCondition' }
    return this.updateObject(data, conditionLocal, names)
  },
  getCheckboxValues(nodes) {
    const result = []
    nodes.forEach((node) => {
      node.querySelectorAll('input[type=checkbox]').forEach((checkbox) => {
        if (checkbox.checked) {
          const name = checkbox.getAttribute('name') || ''
          const match = name.match(/\[[A-Za-z0-9_-]+\]/)
          if (match) {
            result.push(match[0].substr(1, match[0].length - 2))
          }
        }
      })
    })
    return result
  },
  getCheckboxTagValues(nodes) {
    let result = []
    const value = nodes[0] ? nodes[0].value : ''
    if (value.trim() !== '') {
      result = value.split(',')
    }
    return result
  },
  getRadioValues(nodes) {
    const result = []
    nodes.forEach((radio) => {
      if (radio.checked) {
        result.push(radio.getAttribute('value'))
      }
    })
    return result
  },
  getSelectValues(nodes) {
    const result = []
    const value = nodes[0] ? nodes[0].value : ''
    if (value.trim() !== '') {
      result.push(value.trim())
    }
    return result
  },
  getTextValue(nodes) {
    const value = nodes[0] ? nodes[0].value : ''
    return value ? value.trim() : ''
  },
  getTextareaValue(nodes) {
    const field = nodes[0]
    if (!field) return ''
    let { value } = field
    if (field.classList.contains('vditor-html')) {
      // strip all tags first: an editor-empty doc renders as an empty paragraph
      // (exact markup shape isn't guaranteed, so check on text content, not a literal string)
      if (value.replace(/<[^>]+>/g, '').trim() === '') {
        return ''
      }
      value = value.replace(/^<p>/, '').replace(/<\/p>$/, '')
    }
    return value ? value.trim() : ''
  },
  getImageSrc(nodes) {
    const field = nodes[0]
    if (!field) return ''
    let src = ''
    if (field.matches('output')) {
      // image affichée
      const img = field.querySelector('img')
      src = this.getFilename(img ? img.getAttribute('src') : '')
    } else {
      // formulaire affiché avec onglets Téléverser / URL
      const upload = field.querySelector(
        ':scope > #imagebf_image-upload.yw-tabs__pane.active'
      )
      const url = field.querySelector(':scope > #imagebf_image-url.yw-tabs__pane.active')
      const pane = upload || url
      if (pane) {
        const input = pane.querySelector('input')
        src = this.getFilename(input ? input.value : '')
      }
    }
    return src ? src.trim() : ''
  },
  getFileSrc(nodes) {
    const field = nodes[0]
    if (!field) return ''
    let src = ''
    if (field.matches('a')) {
      // lien du fichier affiché
      src = field.getAttribute('download')
      if (!src) {
        // si fichier chargé depuis internet
        src = this.getFilename(field.getAttribute('href'))
      }
    } else {
      // formulaire affiché avec onglets Téléverser / URL
      const upload = field.querySelector(':scope > [id$="-upload"].yw-tabs__pane.active')
      const url = field.querySelector(':scope > [id$="-url"].yw-tabs__pane.active')
      const pane = upload || url
      if (pane) {
        const input = pane.querySelector('input')
        src = this.getFilename(input ? input.value : '')
      }
    }
    return src ? src.trim() : ''
  },
  getFilename(src) {
    return src ? src.split(/[\\/]/).pop().trim() : ''
  },
  getFieldNameValues(fieldName) {
    if (typeof this.fieldNamesCache[fieldName] === 'undefined') {
      return []
    }
    const fieldData = this.fieldNamesCache[fieldName]
    switch (fieldData.type) {
      case 'checkbox':
        return this.getCheckboxValues(fieldData.nodes)
      case 'checkboxtag':
        return this.getCheckboxTagValues(fieldData.nodes)
      case 'radio':
        return this.getRadioValues(fieldData.nodes)
      case 'select':
        return this.getSelectValues(fieldData.nodes)
      case 'text':
        return this.getTextValue(fieldData.nodes)
      case 'textarea':
        return this.getTextareaValue(fieldData.nodes)
      case 'image':
        return this.getImageSrc(fieldData.nodes)
      case 'file':
        return this.getFileSrc(fieldData.nodes)
      default:
        break
    }
    return []
  },
  extractValues(values) {
    if (values.trim() === '') {
      return []
    }
    let tempValues = values.trim()
    if (tempValues.substr(0, 1) === '[' && tempValues.substr(-1) === ']') {
      tempValues = tempValues.substr(1, tempValues.length - 2)
    }
    return tempValues.split(',')
  },
  commonForOperations(fieldName, values, extract) {
    const extractedValues = this.extractValues(values)
    extractedValues.forEach((value) => {
      if (extract.uniqueValues.indexOf(value.trim()) === -1) {
        extract.uniqueValues.push(value.trim())
      }
    })
    const fieldValues = this.getFieldNameValues(fieldName)
    Array.from(fieldValues).forEach((value) => {
      if (extract.uniqueFieldValues.indexOf(value.trim()) === -1) {
        extract.uniqueFieldValues.push(value.trim())
      }
    })
  },
  isLength(fieldName, values, operation) {
    if (Number.isNaN(Number(values))) {
      return false
    }
    const { length } = this.getFieldNameValues(fieldName)
    const number = Number(values)
    switch (operation) {
      case '==': return length === number
      case '!=': return length !== number
      case '<': return length < number
      case '<=': return length <= number
      case '>': return length > number
      case '>=': return length >= number
      default: return false
    }
  },
  match(fieldName, values) {
    const extract = {
      uniqueValues: [],
      uniqueFieldValues: []
    }

    this.commonForOperations(fieldName, values, extract)
    if (extract.uniqueValues.length !== extract.uniqueFieldValues.length) {
      return false
    }

    const uniqueValuesRE = extract.uniqueValues.map((str) => {
      const regexMatch = str.match(/^\/(.*)\/([a-z]*)$/i)
      return regexMatch ? new RegExp(regexMatch[1], regexMatch[2]) : null
    })

    let result = true
    extract.uniqueFieldValues.forEach((value) => {
      if (!uniqueValuesRE.some((regex) => regex && regex.test(value))) {
        result = false
      }
    })
    return result
  },
  isEqual(fieldName, values) {
    if (
      typeof this.fieldNamesCache[fieldName] !== 'undefined'
      && !this.fieldNamesCache[fieldName].isArray
    ) {
      return this.getFieldNameValues(fieldName) == values // eslint-disable-line eqeqeq
    }
    const extract = {
      uniqueValues: [],
      uniqueFieldValues: []
    }
    this.commonForOperations(fieldName, values, extract)
    if (extract.uniqueValues.length !== extract.uniqueFieldValues.length) {
      return false
    }
    let result = true
    extract.uniqueFieldValues.forEach((value) => {
      if (extract.uniqueValues.indexOf(value) === -1) {
        result = false
      }
    })
    return result
  },
  isUnEqual(fieldName, values) {
    return !this.isEqual(fieldName, values)
  },
  isIn(fieldName, values) {
    if (
      typeof this.fieldNamesCache[fieldName] !== 'undefined'
      && !this.fieldNamesCache[fieldName].isArray
    ) {
      return false
    }
    const extract = {
      uniqueValues: [],
      uniqueFieldValues: []
    }
    this.commonForOperations(fieldName, values, extract)
    if (extract.uniqueFieldValues.length === 0) {
      return false
    }
    let result = false
    extract.uniqueFieldValues.forEach((value) => {
      if (extract.uniqueValues.indexOf(value) > -1) {
        result = true
      }
    })
    return result
  },
  isEmpty(fieldName) {
    return this.isEqual(fieldName, '')
  },
  isNotEmpty(fieldName) {
    return this.isUnEqual(fieldName, '')
  },
  renderCondition(structuredCondition) {
    if (typeof structuredCondition.leftPart !== 'undefined'
            && typeof structuredCondition.rightPart !== 'undefined'
            && typeof structuredCondition.typeOfCondition !== 'undefined') {
      return this.renderConditionSecured(
        structuredCondition.leftPart.trim(),
        structuredCondition.typeOfCondition.trim(),
        structuredCondition.rightPart.trim()
      )
    }

    return ''
  },
  renderConditionSecured(fieldName, condition, values) {
    switch (condition) {
      case 'match':
        return ` this.match("${fieldName}","${values}")`
      case '==':
        return ` this.isEqual("${fieldName}","${values}")`
      case '!=':
        return ` this.isUnEqual("${fieldName}","${values}")`
      case 'in':
        return ` this.isIn("${fieldName}","${values}")`
      case 'is empty':
        return ` this.isEmpty("${fieldName}")`
      case 'is not empty':
        return ` this.isNotEmpty("${fieldName}")`
      case '|length ==':
      case '|length !=':
      case '|length <':
      case '|length <=':
      case '|length >':
      case '|length >=':
        return ` this.isLength("${fieldName}","${values}","${condition.substr('|length '.length)}")`
      case 'false':
        return ' false '
      case 'true':
        return ' true '
      case '':
        return ''
      default:
        break
    }
    return ' false '
  },
  renderBadFormatingError(structuredCondition, conditionData) {
    if (typeof structuredCondition.leftPart !== 'undefined'
      && structuredCondition.leftPart.length !== 0) {
      // eslint-disable-next-line max-len
      console.warn(`Left part ('${structuredCondition.leftPart}') should be empty before '${structuredCondition.operation}' in '${conditionData.condition}'`)
      return true
    }
    return false
  },
  emptyCheckbox(element) {
    element.querySelectorAll('input[type=checkbox]').forEach((checkboxParam) => {
      const checkbox = checkboxParam
      checkbox.checked = false
      this.fireChange(checkbox)
    })
  },
  setDefaultCheckbox(element) {
    element.querySelectorAll('input[type=checkbox]:not([data-default])').forEach((checkboxParam) => {
      const checkbox = checkboxParam
      checkbox.checked = false
      this.fireChange(checkbox)
    })
    element.querySelectorAll('input[type=checkbox][data-default]').forEach((checkboxParam) => {
      const checkbox = checkboxParam
      if (checkbox.dataset.default === 'checked') {
        checkbox.checked = true
        this.fireChange(checkbox)
      }
    })
  },
  emptySelect(element) {
    element.querySelectorAll('select').forEach((selectParam) => {
      const select = selectParam
      select.value = ''
      this.fireChange(select)
    })
  },
  setDefaultSelect(element) {
    element.querySelectorAll('select[data-default]').forEach((selectParam) => {
      const select = selectParam
      if (select.dataset.default !== undefined) {
        select.value = select.dataset.default
        this.fireChange(select)
      }
    })
  },
  emptyTextarea(element) {
    element.querySelectorAll('textarea').forEach((textareaParam) => {
      const textarea = textareaParam
      textarea.value = ''
      this.fireChange(textarea)
    })
  },
  emptyRadio(element) {
    // warning it unselect the radio button but this will not erase previous saved value
    // it is needed to have a new value to erase it
    element.querySelectorAll('input[type=radio]').forEach((radioParam) => {
      const radio = radioParam
      radio.checked = false
      this.fireChange(radio)
    })
  },
  setDefaultRadio(element) {
    element.querySelectorAll('input[type=radio]:not([data-default])').forEach((radioParam) => {
      const radio = radioParam
      radio.checked = false
      this.fireChange(radio)
    })
    element.querySelectorAll('input[type=radio][data-default]').forEach((radioParam) => {
      const radio = radioParam
      if (radio.dataset.default === 'checked') {
        radio.checked = true
        this.fireChange(radio)
      }
    })
  },
  emptyGeocode(element) {
    element.querySelectorAll('div[class*="geocode-input"] input[type=hidden]').forEach((inputParam) => {
      const input = inputParam
      input.value = ''
      this.fireChange(input)
    })
  },
  // yw-tag-input widgets: clearing removes the chips and empties the hidden value;
  // restoring re-creates the chips captured at page load (see snapshotTagsDefaults)
  emptyByTags(element) {
    element.querySelectorAll('[data-yw-tag-input]').forEach((widget) => {
      widget.querySelectorAll('[data-yw-tag-input-chip]').forEach((chip) => chip.remove())
      const hidden = widget.querySelector('[data-yw-tag-input-value]')
      if (hidden) {
        hidden.value = ''
        this.fireChange(hidden)
      }
    })
  },
  setDefaultByTags(element) {
    element.querySelectorAll('[data-yw-tag-input]').forEach((widget) => {
      const defaults = this.tagsDefaultsCache.get(widget)
      const hidden = widget.querySelector('[data-yw-tag-input-value]')
      if (!defaults || !hidden || hidden.value !== '') return
      const search = widget.querySelector('[data-yw-tag-input-search]')
      defaults.forEach(({ id, label }) => {
        const chip = document.createElement('span')
        chip.className = 'yw-tag-input__chip'
        chip.setAttribute('data-yw-tag-input-chip', '')
        chip.dataset.tag = id
        chip.textContent = label

        const remove = document.createElement('button')
        remove.type = 'button'
        remove.className = 'yw-tag-input__chip-remove'
        remove.setAttribute('data-yw-tag-input-remove', '')
        remove.setAttribute('aria-label', 'remove')
        remove.textContent = '×'
        chip.appendChild(remove)

        widget.insertBefore(chip, search)
      })
      hidden.value = defaults.map(({ id }) => id).join(',')
      this.fireChange(hidden)
    })
  },
  snapshotTagsDefaults() {
    document.querySelectorAll('[data-yw-tag-input]').forEach((widget) => {
      const defaults = Array.from(widget.querySelectorAll('[data-yw-tag-input-chip]'))
        .map((chip) => ({ id: chip.dataset.tag, label: chip.textContent.replace(/×$/, '') }))
      this.tagsDefaultsCache.set(widget, defaults)
    })
  },
  emptyOthersInputs(element) {
    element.querySelectorAll(
      'input:not([type=checkbox]):not([type=radio]):not([type=hidden])'
    ).forEach((inputParam) => {
      const input = inputParam
      if (input.closest('[data-yw-tag-input]')) return
      input.value = ''
      this.fireChange(input)
    })
  },
  emptyChildren(element) {
    this.emptyCheckbox(element)
    this.emptySelect(element)
    this.emptyTextarea(element)
    this.emptyRadio(element)
    this.emptyGeocode(element)
    this.emptyByTags(element)
    this.emptyOthersInputs(element)
    // this.emptyImage(element);
    // do not work for FileField also
  },
  setDefaultChildren(element) {
    this.setDefaultSelect(element)
    this.setDefaultRadio(element)
    this.setDefaultCheckbox(element)
    this.setDefaultByTags(element)
  },
  resolveCondition(id, cleanSubelements = true, elemsToCleanParam = {}) {
    const elemsToClean = elemsToCleanParam
    if (typeof this.conditionsCache[id] !== 'undefined') {
      const conditionData = this.conditionsCache[id]
      const stack = Object.values(conditionData.structuredConditions)
      let stringToEval = ''
      let errorFound = false
      while (stack.length > 0 && !errorFound) {
        const structuredCondition = stack[0]
        stack.splice(0, 1)
        switch (structuredCondition.operation) {
          case '(':
          case '!(':
            if (this.renderBadFormatingError(structuredCondition, conditionData)) {
              errorFound = true
            } else {
              stringToEval += structuredCondition.operation
            }
            break
          case 'not(':
          case 'not (':
            if (this.renderBadFormatingError(structuredCondition, conditionData)) {
              errorFound = true
            } else {
              stringToEval = `${stringToEval}!(`
            }
            break
          case ')':
            stringToEval = stringToEval
              + this.renderCondition(structuredCondition) + structuredCondition.operation
            break
          case 'and':
            stringToEval = `${stringToEval + this.renderCondition(structuredCondition)}&&`
            break
          case 'or':
            stringToEval = `${stringToEval + this.renderCondition(structuredCondition)}||`
            break
          default:
            if (stack.length > 0) {
              errorFound = true
              // eslint-disable-next-line max-len
              console.warn(`Unknown operation '${structuredCondition.operation}' in '${conditionData.condition}'`)
            }
            stringToEval += this.renderCondition(structuredCondition)
            break
        }
      }
      let display = false
      try {
        // the condition string is built above exclusively from renderConditionSecured
        // eslint-disable-next-line no-eval
        display = errorFound ? false : eval(stringToEval)
      } catch (error) {
        console.warn(error)
        display = false
      }
      // extract no clean param
      const { node } = conditionData
      const clean = node.dataset.noclean !== 'true'
      if (display) {
        const previousStateVisible = this.isVisible(node)
        node.style.display = ''
        window.dispatchEvent(new Event('resize')) // needed to refresh map for geolocalization
        if (clean && !previousStateVisible) {
          if (cleanSubelements) {
            this.setDefaultChildren(node)
          } else {
            elemsToClean[id] = false
          }
        }
      } else {
        node.style.display = 'none'
        if (clean) {
          if (cleanSubelements) {
            this.emptyChildren(node)
          } else {
            elemsToClean[id] = true
          }
        }
      }
    }
  },
  resolveTrigger(inputId) {
    if (typeof this.triggersCache[inputId] !== 'undefined') {
      const fieldsNames = this.triggersCache[inputId]
      const conditionsIds = []
      fieldsNames.forEach((fieldName) => {
        if (typeof this.fieldNamesCache[fieldName] !== 'undefined') {
          this.fieldNamesCache[fieldName].conditionIds.forEach((id) => {
            if (conditionsIds.indexOf(id) < 0) {
              conditionsIds.push(id)
            }
          })
        }
      })
      conditionsIds.forEach((id) => {
        this.resolveCondition(id)
      })
    }
  },
  registerTrigger(input, fieldName) {
    const inputId = input.getAttribute('id')
    if (typeof this.triggersCache[inputId] === 'undefined') {
      this.triggersCache[inputId] = [fieldName]

      const handler = () => {
        ConditionsChecking.resolveTrigger(inputId)
      }
      input.addEventListener('change', handler)
      input.addEventListener('input', handler)
    } else if (this.triggersCache[inputId].indexOf(fieldName) < 0) {
      this.triggersCache[inputId].push(fieldName)
    }
  },
  findCheckbox(fieldName, result) {
    if (result.type !== '') {
      return result
    }
    const containers = Array.from(document.querySelectorAll(
      `div[class*="group-checkbox-"][class*="${fieldName}"],`
      + `ul[class*="group-checkbox-"][class*="${fieldName}"]`
    )).filter((container) => {
      const classes = (container.getAttribute('class') || '').split(' ')
      return classes.some((className) => className.slice(-fieldName.length) === fieldName)
    })
    if (containers.length > 0) {
      const inputs = []
      containers.forEach((container) => {
        container.querySelectorAll('input[type=checkbox]').forEach((input) => inputs.push(input))
      })
      if (inputs.length > 0) {
        result.type = 'checkbox' // eslint-disable-line no-param-reassign
        result.nodes = containers // eslint-disable-line no-param-reassign
        inputs.forEach((input) => {
          ConditionsChecking.registerTrigger(input, fieldName)
        })
      }
    }
    return result
  },
  findCheckboxTag(fieldName, result) {
    if (result.type !== '') {
      return result
    }
    const node = document.querySelector(`[data-yw-tag-input-value][name$="${fieldName}"]`)
    if (node) {
      result.type = 'checkboxtag' // eslint-disable-line no-param-reassign
      result.nodes = [node] // eslint-disable-line no-param-reassign
      ConditionsChecking.registerTrigger(node, fieldName)
    }
    return result
  },
  findList(fieldName, result) {
    if (result.type !== '') {
      return result
    }
    const node = document.querySelector(`select[name$=${fieldName}]`)
    if (node) {
      result.type = 'select' // eslint-disable-line no-param-reassign
      result.nodes = [node] // eslint-disable-line no-param-reassign
      ConditionsChecking.registerTrigger(node, fieldName)
    }
    return result
  },
  findRadio(fieldName, result) {
    if (result.type !== '') {
      return result
    }
    const inputs = Array.from(document.querySelectorAll(`input[name$=${fieldName}][type=radio]`))
    if (inputs.length > 0) {
      result.type = 'radio' // eslint-disable-line no-param-reassign
      result.nodes = inputs // eslint-disable-line no-param-reassign
      inputs.forEach((input) => {
        ConditionsChecking.registerTrigger(input, fieldName)
      })
    }
    return result
  },
  findText(fieldName, result) {
    if (result.type !== '') {
      return result
    }
    const inputs = Array.from(document.querySelectorAll(
      `input[name$=${fieldName}][type=text], input[name$=${fieldName}][type=date]`
    ))
    if (inputs.length > 0) {
      result.type = 'text' // eslint-disable-line no-param-reassign
      result.isArray = false // eslint-disable-line no-param-reassign
      result.nodes = inputs // eslint-disable-line no-param-reassign
      inputs.forEach((input) => {
        ConditionsChecking.registerTrigger(input, fieldName)
      })
    }
    return result
  },
  findTextarea(fieldName, result) {
    if (result.type !== '') {
      return result
    }
    const inputs = Array.from(document.querySelectorAll(`textarea[name$=${fieldName}]`))
    if (inputs.length > 0) {
      result.type = 'textarea' // eslint-disable-line no-param-reassign
      result.isArray = false // eslint-disable-line no-param-reassign
      result.nodes = inputs // eslint-disable-line no-param-reassign
      inputs.forEach((textarea) => {
        // Gestion de la mise à jour des textareas pour les editeurs Wiki et Wysiwyg
        if (textarea.classList.contains('aceditor-textarea')
          || textarea.classList.contains('vditor-html')) {
          let lastValue = textarea.value
          setInterval(() => {
            if (textarea.value !== lastValue) {
              lastValue = textarea.value
              ConditionsChecking.fireChange(textarea)
            }
          }, ConditionsChecking.checkDelay)
        }
        ConditionsChecking.registerTrigger(textarea, fieldName)
      })
    }
    return result
  },
  findImage(fieldName, result) {
    if (result.type !== '') {
      return result
    }
    const uploadDiv = document.querySelector(`div[id$=image${fieldName}-upload]`)
    const imageOutput = document.querySelector(`output[id$=img-image${fieldName}]`)
    if (uploadDiv || imageOutput) {
      result.type = 'image' // eslint-disable-line no-param-reassign
      result.isArray = false // eslint-disable-line no-param-reassign
      if (uploadDiv) {
        result.nodes = [uploadDiv.parentElement] // eslint-disable-line no-param-reassign
        uploadDiv.querySelectorAll('input').forEach((input) => {
          ConditionsChecking.registerTrigger(input, fieldName)
        })
      } else {
        result.nodes = [imageOutput] // eslint-disable-line no-param-reassign
        ConditionsChecking.registerTrigger(imageOutput, fieldName)
      }
    }
    return result
  },
  findFile(fieldName, result) {
    if (result.type !== '') {
      return result
    }
    const uploadDiv = document.querySelector(`div[id=fichier${fieldName}-upload]`)
    const fileLink = document.querySelector(`a[data-id=${fieldName}]`)
    if (uploadDiv || fileLink) {
      result.type = 'file' // eslint-disable-line no-param-reassign
      result.isArray = false // eslint-disable-line no-param-reassign
      if (uploadDiv) {
        const parent = uploadDiv.parentElement
        result.nodes = [parent] // eslint-disable-line no-param-reassign
        parent.querySelectorAll('input').forEach((input) => {
          ConditionsChecking.registerTrigger(input, fieldName)
        })
      } else {
        result.nodes = [fileLink] // eslint-disable-line no-param-reassign
        ConditionsChecking.registerTrigger(fileLink, fieldName)
      }
    }
    return result
  },
  extractFieldNode(fieldName) {
    let result = {
      type: '',
      isArray: true,
      nodes: [],
      conditionIds: []
    }
    result = this.findCheckbox(fieldName, result)
    result = this.findCheckboxTag(fieldName, result)
    result = this.findRadio(fieldName, result)
    result = this.findList(fieldName, result)
    result = this.findText(fieldName, result)
    result = this.findTextarea(fieldName, result)
    result = this.findImage(fieldName, result)
    result = this.findFile(fieldName, result)

    return result
  },
  registerFieldName(fieldName, id) {
    if (typeof this.fieldNamesCache[fieldName] === 'undefined') {
      this.fieldNamesCache[fieldName] = this.extractFieldNode(fieldName)
      this.fieldNamesCache[fieldName].conditionIds.push(id)
    } else if (this.fieldNamesCache[fieldName].conditionIds.indexOf(id) < 0) {
      this.fieldNamesCache[fieldName].conditionIds.push(id)
    }
  },
  parseCondition(element) {
    const condition = element.dataset.conditionschecking
    // index = internal id
    const id = this.conditionsCache.length
    // save cache
    this.conditionsCache.push({
      condition,
      node: element,
      structuredConditions: {}
    })

    let parsingObject = {
      restOfCondition: condition,
      currentCondition: '',
      operation: ''
    }
    while (parsingObject.restOfCondition.length > 0) {
      parsingObject = this.getFirstOperation(parsingObject)
      // check condition
      const indexForStructuredCondition = Object.keys(
        this.conditionsCache[id].structuredConditions
      ).length
      // save in cache
      const newCondition = { operation: parsingObject.operation }
      this.conditionsCache[id].structuredConditions[indexForStructuredCondition] = newCondition
      let structuredCondition = this.conditionsCache[id]
        .structuredConditions[indexForStructuredCondition]
      if (parsingObject.currentCondition.length > 0) {
        structuredCondition = this.addCondition(
          parsingObject.currentCondition
        )
      } else {
        structuredCondition.leftPart = ''
        structuredCondition.rightPart = ''
        structuredCondition.typeOfCondition = ''
      }
      // activate trigger
      if (typeof structuredCondition.leftPart !== 'undefined'
        && structuredCondition.leftPart.length > 0) {
        const fieldName = structuredCondition.leftPart.trim()
        this.registerFieldName(fieldName, id)
      }
      Object.keys(structuredCondition).forEach((key) => {
        this.conditionsCache[id]
          .structuredConditions[indexForStructuredCondition][key] = structuredCondition[key]
      })
    }
  },
  init() {
    this.snapshotTagsDefaults()
    document.querySelectorAll('div[data-conditionschecking]').forEach((element) => {
      this.parseCondition(element)
    })
    // init conditions
    const elemsToClean = {}
    for (let index = 0; index < this.conditionsCache.length; index += 1) {
      this.resolveCondition(index, false, elemsToClean)
    }
    Object.keys(elemsToClean).forEach((id) => {
      if (elemsToClean[id]) {
        const conditionData = this.conditionsCache[id]
        this.emptyChildren(conditionData.node)
      }
    })
  }
}

ConditionsChecking.init()
