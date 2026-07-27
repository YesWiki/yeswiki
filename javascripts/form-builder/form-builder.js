// form-builder.js — vanilla form designer (ticket 26)
//
// Elementor-style layout: a left sidebar that shows either the field palette or the
// selected field's settings, and a canvas listing the form's fields as cards
// (drag-to-reorder via the vendored SortableJS, loaded globally as window.Sortable).
//
// The designer edits the stored JSON template (`bn_template`, an array of
// named-attribute field objects) directly: converter.js only resolves wiki type
// keywords to designer configs and back, there is no positional mapping. The
// #form-builder-text textarea (the "code" tab) is the single source of truth on
// submit; the designer serializes into it on every change.
import registry, { paletteEntries } from './registry.js'
import { parseFields, serializeFields } from './converter.js'

const container = document.getElementById('form-builder-container')
const textarea = document.getElementById('form-builder-text')

function esc(value) {
  const div = document.createElement('div')
  div.textContent = String(value ?? '')
  return div.innerHTML
}

function el(html) {
  const template = document.createElement('template')
  template.innerHTML = html.trim()
  return template.content.firstElementChild
}

// ---------------------------------------------------------------- state

let fields = [] // [{ id, type, data }]
let selectedId = null
let uid = 0

function newId() {
  uid += 1
  return `fbf${uid}`
}

function selectedField() {
  return fields.find((field) => field.id === selectedId) || null
}

// base attributes every field gets unless overridden by the config or disabled
function baseAttributes() {
  return {
    label: { label: _t('FORM_BUILDER_LABEL_LABEL'), value: '' },
    name: { label: _t('FORM_BUILDER_NAME_LABEL'), value: '' },
    default: { label: _t('FORM_BUILDER_DEFAULT_LABEL'), value: '' },
    required: { label: _t('FORM_BUILDER_REQUIRED_LABEL'), options: { '': _t('NO'), 1: _t('YES') } }
  }
}

function attributeDefs(config) {
  const disabled = config.disabledAttributes || []
  const defs = { ...baseAttributes(), ...(config.attributes || {}) }
  disabled.forEach((name) => delete defs[name])
  return defs
}

function uniqueName(base) {
  const names = fields.map((field) => field.data.name)
  if (!names.includes(base)) return base
  let i = 2
  while (names.includes(`${base}_${i}`)) i += 1
  return `${base}_${i}`
}

function makeField(type, extraData = {}) {
  const config = registry[type] || registry.custom
  const data = {}
  Object.entries(attributeDefs(config)).forEach(([name, def]) => {
    if (def.transient) return
    if (def.value !== undefined && def.value !== '') {
      data[name] = def.value
    } else if (def.options && !('' in def.options) && name !== 'required') {
      [data[name]] = Object.keys(def.options)
    }
  })
  Object.assign(data, extraData)
  if (!data.label && config.field?.label && !((config.disabledAttributes || []).includes('label'))) {
    data.label = config.field.label
  }
  if (!data.name && !((config.disabledAttributes || []).includes('name'))) {
    data.name = uniqueName(config.defaultIdentifier || `bf_${type.replace(/[^a-z0-9]/gi, '')}`)
  }
  return { id: newId(), type, data }
}

// ---------------------------------------------------------------- sync

function serialize() {
  return serializeFields(fields.map(({ type, data }) => ({ type, data })), registry)
}

function syncToTextarea() {
  textarea.value = serialize()
}

function loadFromTextarea() {
  const parsed = parseFields(textarea.value, registry)
  if (parsed === null) return false
  fields = parsed.map(({ type, data }) => ({ id: newId(), type, data }))
  return true
}

// ---------------------------------------------------------------- layout

let paletteEl
let settingsEl
let canvasEl
let errorEl

function boot() {
  container.classList.add('fb')
  container.innerHTML = ''
  errorEl = el('<div class="fb__error hide"></div>')
  paletteEl = el(`<div class="fb__palette">
    <h3 class="fb__sidebar-title">${_t('FORM_BUILDER_ADD_FIELDS')}</h3>
    <div class="fb__palette-grid"></div>
  </div>`)
  settingsEl = el('<div class="fb__settings hide"></div>')
  canvasEl = el('<div class="fb__canvas"></div>')
  const sidebar = el('<aside class="fb__sidebar"></aside>')
  sidebar.append(paletteEl, settingsEl)
  container.append(errorEl, sidebar, canvasEl)

  renderPalette()
  if (!loadFromTextarea()) {
    showError(`${_t('FORM_BUILDER_INVALID_JSON')}`)
    fields = []
  }
  renderCanvas()
  initCanvasSort()
  bindTabsAndSubmit()
}

function showError(message) {
  errorEl.textContent = message
  errorEl.classList.remove('hide')
}

function clearError() {
  errorEl.classList.add('hide')
}

// ---------------------------------------------------------------- palette

function renderPalette() {
  const grid = paletteEl.querySelector('.fb__palette-grid')
  paletteEntries.forEach(({ type, config }) => {
    const entry = config.set || config.field
    const item = el(`<button type="button" class="fb__palette-item" data-fb-type="${esc(type)}">
      <span class="fb__palette-icon">${(config.field || config.set).icon || ''}</span>
      <span class="fb__palette-label">${esc(entry.label)}</span>
    </button>`)
    item.addEventListener('click', () => addFromPalette(type))
    grid.append(item)
  })

  if (window.Sortable) {
    window.Sortable.create(grid, {
      group: { name: 'fb', pull: 'clone', put: false },
      sort: false,
      animation: 150
    })
  }
}

function addFromPalette(type, index = fields.length) {
  const config = registry[type]
  const added = []
  if (config.set) {
    config.set.fields.forEach((preset) => {
      const { type: presetType, ...presetData } = preset
      added.push(makeField(presetType, presetData))
    })
  } else {
    added.push(makeField(type))
  }
  fields.splice(index, 0, ...added)
  syncToTextarea()
  renderCanvas()
  selectField(added[0].id)
}

// ---------------------------------------------------------------- canvas

function initCanvasSort() {
  if (!window.Sortable) return
  window.Sortable.create(canvasEl, {
    group: 'fb',
    handle: '.fb__card-drag',
    animation: 150,
    onAdd(event) {
      // dropped from the palette: replace the clone with a real field
      const type = event.item.getAttribute('data-fb-type')
      event.item.remove()
      addFromPalette(type, event.newIndex)
    },
    onUpdate(event) {
      const [moved] = fields.splice(event.oldIndex, 1)
      fields.splice(event.newIndex, 0, moved)
      syncToTextarea()
    }
  })
}

function defaultPreview(type, data) {
  switch (type) {
    case 'textarea':
      return `<textarea rows="${esc(data.num_rows || 3)}" placeholder="${esc(data.placeholder || '')}"></textarea>`
    case 'select':
      return `<select><option>${esc(data.linked_object || '')}</option></select>`
    case 'checkbox-group':
      return `<label><input type="checkbox"/> ${esc(data.linked_object || '…')}</label>`
    case 'radio-group':
      return `<label><input type="radio"/> ${esc(data.linked_object || '…')}</label>`
    case 'date':
      return '<input type="date"/>'
    case 'file':
      return '<input type="file"/>'
    default:
      return '<input type="text"/>'
  }
}

function cardPreview(field) {
  const config = registry[field.type] || registry.custom
  let html
  if (config.renderInput) {
    html = config.renderInput(field.data)?.field ?? ''
  } else {
    html = defaultPreview(field.type, field.data)
  }
  return html
}

function renderCanvas() {
  canvasEl.innerHTML = ''
  if (fields.length === 0) {
    canvasEl.append(el(`<div class="fb__empty" data-fb-keep>${_t('FORM_BUILDER_EMPTY')}</div>`))
  }
  fields.forEach((field) => canvasEl.append(renderCard(field)))
}

function renderCard(field) {
  const config = registry[field.type] || registry.custom
  const icon = (config.field || config.set || {}).icon || ''
  const showLabel = !((config.disabledAttributes || []).includes('label'))
  const card = el(`<div class="fb__card${field.id === selectedId ? ' fb__card--selected' : ''}" data-fb-id="${field.id}">
    <div class="fb__card-header">
      <span class="fb__card-drag" title="⇕">⠿</span>
      <span class="fb__card-icon">${icon}</span>
      <span class="fb__card-title">${showLabel ? esc(field.data.label || '') : esc((config.field || config.set || {}).label || field.type)}</span>
      ${field.data.name ? `<code class="fb__card-name">${esc(field.data.name)}</code>` : ''}
      ${field.data.required === '1' ? '<span class="fb__card-required">*</span>' : ''}
      <span class="fb__card-actions">
        <button type="button" class="fb__card-action" data-fb-action="duplicate" title="${_t('FORM_BUILDER_DUPLICATE')}"><i class="far fa-copy"></i></button>
        <button type="button" class="fb__card-action" data-fb-action="delete" title="${_t('FORM_BUILDER_DELETE')}"><i class="far fa-trash-alt"></i></button>
      </span>
    </div>
    <div class="fb__card-body">
      <div class="fb__card-preview">${cardPreview(field)}</div>
      ${field.data.hint ? `<small class="fb__card-hint">${esc(field.data.hint)}</small>` : ''}
    </div>
  </div>`)

  card.addEventListener('click', (event) => {
    const action = event.target.closest('[data-fb-action]')?.getAttribute('data-fb-action')
    if (action === 'delete') {
      fields = fields.filter((other) => other.id !== field.id)
      if (selectedId === field.id) closeSettings()
      syncToTextarea()
      renderCanvas()
      return
    }
    if (action === 'duplicate') {
      const index = fields.findIndex((other) => other.id === field.id)
      const copy = { id: newId(), type: field.type, data: { ...field.data } }
      if (copy.data.name) copy.data.name = uniqueName(copy.data.name)
      fields.splice(index + 1, 0, copy)
      syncToTextarea()
      renderCanvas()
      selectField(copy.id)
      return
    }
    selectField(field.id)
  })
  return card
}

function refreshCard(field) {
  const existing = canvasEl.querySelector(`[data-fb-id="${field.id}"]`)
  if (existing) existing.replaceWith(renderCard(field))
}

// ---------------------------------------------------------------- settings panel

function selectField(id) {
  selectedId = id
  canvasEl.querySelectorAll('.fb__card').forEach((card) => {
    card.classList.toggle('fb__card--selected', card.getAttribute('data-fb-id') === id)
  })
  renderSettings()
  paletteEl.classList.add('hide')
  settingsEl.classList.remove('hide')
}

function closeSettings() {
  selectedId = null
  settingsEl.classList.add('hide')
  paletteEl.classList.remove('hide')
  canvasEl.querySelectorAll('.fb__card--selected').forEach((card) => card.classList.remove('fb__card--selected'))
}

function controlFor(name, def, value) {
  if (def.options && def.multiple) {
    const values = Array.isArray(value) ? value : String(value ?? '').split(',').map((part) => part.trim()).filter(Boolean)
    const group = el(`<div class="fb__checks" data-fb-input="${esc(name)}"></div>`)
    Object.entries(def.options).forEach(([optionValue, optionLabel]) => {
      const checkbox = el(`<label class="fb__check"><input type="checkbox" value="${esc(optionValue)}"${values.includes(optionValue) ? ' checked' : ''}/> ${esc(optionLabel)}</label>`)
      group.append(checkbox)
    })
    return group
  }
  if (def.options) {
    const select = el(`<select class="yw-input" data-fb-input="${esc(name)}"></select>`)
    Object.entries(def.options).forEach(([optionValue, optionLabel]) => {
      const option = el(`<option value="${esc(optionValue)}">${esc(optionLabel)}</option>`)
      if (String(value ?? '') === String(optionValue)) option.selected = true
      select.append(option)
    })
    return select
  }
  if (def.type === 'textarea') {
    const area = el(`<textarea class="yw-input" rows="${esc(def.rows || 4)}" data-fb-input="${esc(name)}"></textarea>`)
    area.value = String(value ?? '')
    return area
  }
  if (def.type === 'file') {
    const wrap = el(`<div class="fb__file" data-fb-input="${esc(name)}"></div>`)
    const current = String(value ?? '')
    if (current.includes('|data:')) {
      wrap.append(el(`<img class="fb__file-preview" src="${esc(current.split('|')[1])}" alt=""/>`))
    }
    wrap.append(el(`<input type="file"${def.accept ? ` accept="${esc(def.accept)}"` : ''}/>`))
    return wrap
  }
  const input = el(`<input class="yw-input" type="${def.type === 'number' ? 'number' : 'text'}" data-fb-input="${esc(name)}"${def.placeholder ? ` placeholder="${esc(def.placeholder)}"` : ''}/>`)
  input.value = String(value ?? '')
  return input
}

function renderSettings() {
  const field = selectedField()
  if (!field) return
  const config = registry[field.type] || registry.custom
  const defs = attributeDefs(config)
  const advanced = config.advancedAttributes || []

  settingsEl.innerHTML = ''
  const title = (config.field || config.set || {}).label || field.type
  settingsEl.append(el(`<div class="fb__settings-header">
    <button type="button" class="yw-btn yw-btn--sm fb__back">← ${_t('FORM_BUILDER_BACK')}</button>
    <h3 class="fb__sidebar-title">${esc(title)}</h3>
  </div>`))
  settingsEl.querySelector('.fb__back').addEventListener('click', closeSettings)

  if (config.editorHint) {
    settingsEl.append(el(`<div class="fb__hint">${config.editorHint}</div>`))
  }

  const main = el('<div class="fb__attributes"></div>')
  const advancedWrap = el(`<details class="fb__advanced"><summary>${_t('FORM_BUILDER_ADVANCED')}</summary></details>`)
  const changeCallbacks = {}

  Object.entries(defs).forEach(([name, def]) => {
    if (name === 'separator') {
      main.append(el('<hr class="fb__separator"/>'))
      return
    }
    const row = el(`<div class="fb__attribute" data-fb-attribute="${esc(name)}">
      <label class="yw-form-label">${esc(def.label ?? name)}</label>
    </div>`)
    row.append(controlFor(name, def, field.data[name] ?? def.value ?? ''))
    if (def.description) row.append(el(`<small class="fb__description">${esc(def.description)}</small>`));
    (advanced.includes(name) ? advancedWrap : main).append(row)
  })

  settingsEl.append(main)
  if (advancedWrap.childElementCount > 1) settingsEl.append(advancedWrap)

  // ------------------------------------------------ editorSetup api
  const readControl = (name) => {
    const control = settingsEl.querySelector(`[data-fb-input="${name}"]`)
    if (!control) return undefined
    if (control.classList.contains('fb__checks')) {
      return Array.from(control.querySelectorAll('input:checked')).map((box) => box.value)
    }
    if (control.classList.contains('fb__file')) return field.data[name] ?? ''
    return control.value
  }

  const writeControl = (name, value) => {
    const control = settingsEl.querySelector(`[data-fb-input="${name}"]`)
    if (!control) return
    if (control.classList.contains('fb__checks')) {
      const values = Array.isArray(value) ? value : String(value ?? '').split(',').map((part) => part.trim()).filter(Boolean)
      control.querySelectorAll('input').forEach((box) => {
        box.checked = values.includes(box.value) // eslint-disable-line no-param-reassign
      })
      return
    }
    if (control.classList.contains('fb__file')) return
    control.value = String(value ?? '')
  }

  const commit = (name, value) => {
    const serialized = Array.isArray(value) ? value.join(',') : String(value ?? '')
    if (serialized === '') delete field.data[name]
    else field.data[name] = serialized
    syncToTextarea()
    refreshCard(field)
  }

  const api = {
    getValue: (name) => {
      const def = defs[name]
      if (def?.multiple) {
        const raw = readControl(name) ?? field.data[name] ?? ''
        return Array.isArray(raw) ? raw : String(raw).split(',').map((part) => part.trim()).filter(Boolean)
      }
      return readControl(name) ?? field.data[name] ?? ''
    },
    setValue: (name, value, { silent = false } = {}) => {
      writeControl(name, value)
      commit(name, value)
      if (!silent) (changeCallbacks[name] || []).forEach((callback) => callback())
    },
    show: (name) => settingsEl.querySelector(`[data-fb-attribute="${name}"]`)?.classList.remove('hide'),
    hide: (name) => settingsEl.querySelector(`[data-fb-attribute="${name}"]`)?.classList.add('hide'),
    setLabel: (name, text) => {
      const label = settingsEl.querySelector(`[data-fb-attribute="${name}"] > label`)
      if (label) label.textContent = text
    },
    // rebuilds a select attribute's option list (the current value stays selected,
    // and is kept as an extra option if the new list doesn't contain it)
    setOptions: (name, options) => {
      const control = settingsEl.querySelector(`select[data-fb-input="${name}"]`)
      if (!control) return
      const current = control.value || String(field.data[name] ?? '')
      const entries = Object.entries(options)
      if (current && !entries.some(([optionValue]) => optionValue === current)) {
        entries.unshift([current, current])
      }
      control.innerHTML = ''
      entries.forEach(([optionValue, optionLabel]) => {
        const option = el(`<option value="${esc(optionValue)}">${esc(optionLabel)}</option>`)
        if (current === String(optionValue)) option.selected = true
        control.append(option)
      })
    },
    // the settings-panel row of an attribute, for configs that add their own
    // adjacent controls (e.g. the enum family's create/edit-list buttons)
    getRow: (name) => settingsEl.querySelector(`[data-fb-attribute="${name}"]`),
    onChange: (name, callback) => {
      (changeCallbacks[name] = changeCallbacks[name] || []).push(callback)
    }
  }

  settingsEl.querySelectorAll('[data-fb-input]').forEach((control) => {
    const name = control.getAttribute('data-fb-input')
    if (control.classList.contains('fb__file')) {
      control.querySelector('input[type=file]').addEventListener('change', (event) => {
        const file = event.target.files[0]
        if (!file) {
          commit(name, '')
          return
        }
        const reader = new FileReader()
        reader.onload = () => {
          commit(name, `${file.name}|${reader.result}`);
          (changeCallbacks[name] || []).forEach((callback) => callback())
        }
        reader.readAsDataURL(file)
      })
      return
    }
    const handler = () => {
      commit(name, readControl(name));
      (changeCallbacks[name] || []).forEach((callback) => callback())
    }
    control.addEventListener(control.matches('select, .fb__checks') ? 'change' : 'input', handler)
    if (control.classList.contains('fb__checks')) {
      control.querySelectorAll('input').forEach((box) => box.addEventListener('change', handler))
    }
  })

  config.editorSetup?.(api)
}

// ---------------------------------------------------------------- tabs + submit

function bindTabsAndSubmit() {
  // switching TO the designer tab re-reads the textarea (the code tab may have
  // changed it); switching AWAY serializes. Invalid JSON keeps the current
  // designer state and shows an error.
  document.querySelectorAll('a[href="#formbuilder"]').forEach((link) => {
    link.addEventListener('click', () => {
      if (textarea.value.trim() === '' || textarea.value.trim() === serialize().trim()) return
      if (loadFromTextarea()) {
        clearError()
        closeSettings()
        renderCanvas()
      } else {
        showError(`${_t('FORM_BUILDER_INVALID_JSON')}`)
      }
    })
  })
  document.querySelectorAll('a[href="#code"]').forEach((link) => {
    link.addEventListener('click', () => syncToTextarea())
  })
  textarea.form?.addEventListener('submit', () => {
    // the designer tab may hold unserialized state only when it is the active
    // pane; the textarea already mirrors every designer change otherwise
    const designerPane = document.getElementById('formbuilder')
    if (designerPane?.classList.contains('active')) syncToTextarea()
  })
}

if (container && textarea) {
  boot()
}
