// form-builder.js — vanilla form designer (ticket 26)
//
// Elementor-style layout: a left sidebar that shows either the field palette or the
// selected field's settings, and a canvas listing the form's fields as cards
// (drag-to-reorder via the vendored SortableJS, loaded globally as window.Sortable).
//
// The designer edits the stored JSON template (`template`, an array of
// named-attribute field objects) directly: converter.js only resolves wiki type
// keywords to designer configs and back, there is no positional mapping. The
// #form-builder-text textarea (the "code" tab) is the single source of truth on
// submit; the designer serializes into it on every change.
import registry, { paletteEntries } from './registry.js'
import { parseFields, serializeFields } from './converter.js'

const container = document.getElementById('form-builder-container')
const textarea = document.getElementById('form-builder-text')

// Fields of a Content type's mandatory core structure (ticket 10). They are ordinary
// fields here -- relabel, reorder, change the help text or the ACL freely -- except that
// they cannot be deleted or duplicated. The server enforces the same rule whatever this
// UI does, so this is the honest affordance, not the protection.
const lockedFieldNames = (() => {
  try {
    const declared = JSON.parse(container?.dataset.lockedFields || '[]')
    return Array.isArray(declared) ? declared : []
  } catch {
    return []
  }
})()

function isLocked(field) {
  return lockedFieldNames.includes(field?.data?.name)
}

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

function splitCsv(value) {
  return Array.isArray(value)
    ? value
    : String(value ?? '').split(',').map((part) => part.trim()).filter(Boolean)
}

function joinCsv(value) {
  return Array.isArray(value) ? value.join(',') : String(value ?? '')
}

// a field's config, falling back to the generic numeric-keys editor for
// unknown/extension types
function configFor(type) {
  return registry[type] || registry.custom
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
  const config = configFor(type)
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
  updateTitleSelect()
  updateRoleSelects()
}

// ---------------------------------------------------------------- entry title
// The "field used as title" select under the builder (entry_title_template,
// ADR-0010): its options mirror the designer's current named fields; picking one
// stores `{{name}}`, the custom option reveals the free-template input.

const CUSTOM_TITLE = '__custom__'
const titleSelect = document.getElementById('entry-title-select')
const titleCustom = document.getElementById('entry-title-custom')

function updateTitleSelect() {
  if (!titleSelect || !titleCustom) return
  const current = titleCustom.value.trim()
  titleSelect.innerHTML = ''
  let matched = false
  fields.forEach(({ data }) => {
    if (!data.name) return
    const option = document.createElement('option')
    option.value = `{{${data.name}}}`
    option.textContent = data.label ? `${data.label} (${data.name})` : data.name
    if (current === option.value) {
      option.selected = true
      matched = true
    }
    titleSelect.append(option)
  })
  const custom = document.createElement('option')
  custom.value = CUSTOM_TITLE
  custom.textContent = _t('FORM_EDIT_CUSTOM_TITLE')
  if (!matched) custom.selected = true
  titleSelect.append(custom)
  titleCustom.classList.toggle('hide', matched)
}

// ---------------------------------------------------------------- field roles
// Which field answers each of core's questions (ticket 11). A role left on
// "automatic" resolves from the field's own type, so these selects only have to
// say something when a form is ambiguous or the webmaster wants another field --
// which is why the empty option is the default and is not an error.

function updateRoleSelects() {
  document.querySelectorAll('[data-yw-field-role]').forEach((selectParam) => {
    const select = selectParam
    const types = (select.dataset.ywRoleTypes || '').split(',').filter((t) => t.length > 0)
    const chosen = select.value || select.dataset.ywRoleCurrent || ''
    select.innerHTML = ''

    const auto = document.createElement('option')
    auto.value = ''
    auto.textContent = _t('FORM_EDIT_FIELD_ROLE_AUTOMATIC')
    select.append(auto)

    fields.forEach(({ type, data }) => {
      // only fields that can play the role: an explicit mapping to anything else is
      // refused server-side, so offering it would only be a way to fail
      if (!data.name || (types.length > 0 && !types.includes(type))) return
      const option = document.createElement('option')
      option.value = data.name
      option.textContent = data.label ? `${data.label} (${data.name})` : data.name
      option.selected = chosen === data.name
      select.append(option)
    })
  })
}

function bindTitleSelect() {
  if (!titleSelect || !titleCustom) return
  titleSelect.addEventListener('change', () => {
    if (titleSelect.value === CUSTOM_TITLE) {
      titleCustom.classList.remove('hide')
      titleCustom.focus()
    } else {
      titleCustom.value = titleSelect.value
      titleCustom.classList.add('hide')
    }
  })
  updateTitleSelect()
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
  container.classList.add('yw-fb')
  container.innerHTML = ''
  errorEl = el('<div class="yw-fb__error hide"></div>')
  paletteEl = el(`<div class="yw-fb__palette">
    <h3 class="yw-fb__sidebar-title">${_t('FORM_BUILDER_ADD_FIELDS')}</h3>
    <div class="yw-fb__palette-grid"></div>
  </div>`)
  settingsEl = el('<div class="yw-fb__settings hide"></div>')
  canvasEl = el('<div class="yw-fb__canvas"></div>')
  const sidebar = el('<aside class="yw-fb__sidebar"></aside>')
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
  bindTitleSelect()
  initDrawer()
}

function showError(message) {
  errorEl.textContent = message
  errorEl.classList.remove('hide')
}

function clearError() {
  errorEl.classList.add('hide')
}

// ---------------------------------------------------------------- palette

// case- and diacritic-insensitive match ("hidden" finds "Champ caché"'s
// normalized form too — matching happens on the translated label)
function normalizeForFilter(text) {
  return String(text).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
}

function renderPalette() {
  const filter = el(`<input type="search" class="yw-input yw-fb__filter" placeholder="${esc(_t('FORM_BUILDER_FILTER'))}"/>`)
  paletteEl.insertBefore(filter, paletteEl.querySelector('.yw-fb__palette-grid'))

  const grid = paletteEl.querySelector('.yw-fb__palette-grid')
  paletteEntries.forEach(({ type, config }) => {
    const entry = config.set || config.field
    const item = el(`<button type="button" class="yw-fb__palette-item" data-fb-type="${esc(type)}">
      <span class="yw-fb__palette-icon">${(config.field || config.set).icon || ''}</span>
      <span class="yw-fb__palette-label">${esc(entry.label)}</span>
    </button>`)
    item.addEventListener('click', () => addFromPalette(type))
    grid.append(item)
  })

  // live filter on the palette only — a selected field's settings are never filtered
  filter.addEventListener('input', () => {
    const needle = normalizeForFilter(filter.value.trim())
    grid.querySelectorAll('.yw-fb__palette-item').forEach((item) => {
      const label = item.querySelector('.yw-fb__palette-label').textContent
      item.classList.toggle('hide', needle !== '' && !normalizeForFilter(label).includes(needle))
    })
  })

  if (window.Sortable) {
    window.Sortable.create(grid, {
      group: { name: 'fb', pull: 'clone', put: false },
      sort: false,
      animation: 150
    })
  }
}

// narrow screens: the sidebar is a right-side drawer behind a floating button
function initDrawer() {
  const toggle = el(`<button type="button" class="yw-btn yw-btn--primary yw-fb__drawer-toggle"
    aria-expanded="false" title="${esc(_t('FORM_BUILDER_ADD_FIELDS'))}"><svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#plus"/></svg></button>`)
  toggle.addEventListener('click', () => {
    const open = container.classList.toggle('yw-fb--drawer-open')
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false')
  })
  container.append(toggle)
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

// ---------------------------------------------------------------- previews
// A card body shows the field exactly as the entry form will render it: the
// designer posts its template to `api/forms/preview`, which runs every field
// object through FormManager::prepareData + BazarField::renderInputIfPermitted
// and answers with the real Twig markup as htmx out-of-band swaps, one per card.
//
// Ticket 14: the answer also carries the field's declared assets, so a map preview
// actually becomes a map. Deduplicating them across cards is core's job now
// (javascripts/yw-assets.js), not this file's.
//
// Previews are cached per field id so rebuilding a card (every keystroke goes
// through refreshCard) never blanks it: the stale markup stays on screen, dimmed,
// until the answer for that field lands.

const PREVIEW_DEBOUNCE = 300

const previewHtml = {} // field id -> last markup rendered by the server
const previewRequests = {} // field id -> id of the last request that asked for it
let previewTimer = null
let previewRequestId = 0
let previewPendingAll = false
let previewPendingIds = new Set()

function previewHolder(field) {
  return canvasEl.querySelector(`[data-fb-id="${field.id}"] .yw-fb__card-preview`)
}

// The previews live inside the form-edit <form>: their controls must never be submitted with
// it, block its validation, or duplicate an element id.
//
// Ids are rewritten rather than removed (ticket 14). Removing them made every preview inert
// by accident — a field initialiser that looks its parts up by id found nothing — while the
// actual requirement is only that they stay unique within the page. Prefixing with the card
// id satisfies both, and `for` attributes are remapped so labels keep working.
function neutralizePreview(holder, fieldId) {
  holder.querySelectorAll('input, select, textarea, button').forEach((control) => {
    control.disabled = true // eslint-disable-line no-param-reassign
    control.removeAttribute('name')
    control.removeAttribute('required')
  })

  const prefix = `${fieldId}__`
  holder.querySelectorAll('[id]').forEach((node) => {
    if (node.id.startsWith(prefix)) return
    node.id = prefix + node.id // eslint-disable-line no-param-reassign
  })
  holder.querySelectorAll('[for]').forEach((node) => {
    const target = node.getAttribute('for')
    if (target && !target.startsWith(prefix)) node.setAttribute('for', prefix + target)
  })
}

// some inputs draw nothing by themselves — a hidden field, or a widget the entry
// form builds in JS the designer does not run — and would leave a blank card body
function hasVisiblePreview(holder) {
  return holder.textContent.trim() !== ''
    || holder.querySelector('input:not([type="hidden"]), select, textarea, img, svg, canvas, iframe') !== null
}

function paintPreview(holder, field) {
  holder.innerHTML = previewHtml[field.id] ?? '' // eslint-disable-line no-param-reassign
  neutralizePreview(holder, field.id)
  if (!hasVisiblePreview(holder)) {
    // eslint-disable-next-line no-param-reassign
    holder.innerHTML = `<em class="yw-fb__card-nopreview">${esc(_t('FORM_BUILDER_NO_PREVIEW'))}</em>`
  }
  holder.classList.remove('yw-fb__card-preview--pending')
}

function forgetStalePreviews() {
  const alive = new Set(fields.map((field) => field.id))
  Object.keys(previewHtml).forEach((id) => {
    if (!alive.has(id)) {
      delete previewHtml[id]
      delete previewRequests[id]
    }
  })
}

// `ids` null asks for every field, otherwise only the listed ones (an attribute
// edit changes one card). Calls coalesce until the debounce elapses.
function schedulePreviews(ids = null) {
  if (ids === null) previewPendingAll = true
  else ids.forEach((id) => previewPendingIds.add(id))
  clearTimeout(previewTimer)
  previewTimer = setTimeout(runPreviews, PREVIEW_DEBOUNCE)
}

async function runPreviews() {
  const targets = previewPendingAll
    ? fields.slice()
    : fields.filter((field) => previewPendingIds.has(field.id))
  previewPendingAll = false
  previewPendingIds = new Set()
  if (targets.length === 0) return

  previewRequestId += 1
  const requestId = previewRequestId
  targets.forEach((field) => {
    previewRequests[field.id] = requestId
    previewHolder(field)?.classList.add('yw-fb__card-preview--pending')
  })

  const template = serializeFields(targets.map(({ type, data }) => ({ type, data })), registry)
  const ids = JSON.stringify(targets.map((field) => field.id))

  // Ticket 14: the answer is markup, not JSON. htmx distributes it — one out-of-band swap
  // per field, addressed by the card's own id, plus one carrying whatever assets the input
  // templates declared (leaflet for a map, vditor for a long text), which swap into <head>.
  // Addressing by id rather than by position also removes the old hazard that one
  // unbuildable field shifted every card after it.
  try {
    await htmx.ajax('POST', wiki.url('?api/forms/preview'), {
      source: canvasEl,
      swap: 'none',
      values: { template, ids }
    })
  } catch (error) {
    // keep whatever the cards already show rather than blanking the canvas
    console.error('form designer: field preview request failed', error)
    targets.forEach((field) => {
      if (previewRequests[field.id] === requestId) {
        previewHolder(field)?.classList.remove('yw-fb__card-preview--pending')
      }
    })
    return
  }

  targets.forEach((field) => {
    // a later edit already asked for this field again: its answer wins. htmx has already
    // written into the holder, so a stale response is undone by repainting from the cache
    // the fresher request will fill.
    const holder = previewHolder(field)
    if (!holder) return
    if (previewRequests[field.id] !== requestId) {
      paintPreview(holder, field)
      return
    }
    // cache what the swap just put there, so rebuilding the card (every keystroke goes
    // through refreshCard) repaints instantly instead of blanking
    previewHtml[field.id] = holder.innerHTML
    paintPreview(holder, field)
  })
}

// ---------------------------------------------------------------- canvas

function initCanvasSort() {
  if (!window.Sortable) return
  window.Sortable.create(canvasEl, {
    group: 'fb',
    handle: '.yw-fb__card-drag',
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

function renderCanvas() {
  canvasEl.innerHTML = ''
  if (fields.length === 0) {
    canvasEl.append(el(`<div class="yw-fb__empty" data-fb-keep>${_t('FORM_BUILDER_EMPTY')}</div>`))
  }
  fields.forEach((field) => canvasEl.append(renderCard(field)))
  forgetStalePreviews()
  // cards rebuilt from the cache paint instantly; the ones never previewed yet
  // (just added, duplicated, or reloaded from the code tab) need a render
  const missing = fields.filter((field) => previewHtml[field.id] === undefined).map((field) => field.id)
  if (missing.length > 0) schedulePreviews(missing)
}

function renderCard(field) {
  const config = configFor(field.type)
  const icon = (config.field || config.set || {}).icon || ''
  const showLabel = !((config.disabledAttributes || []).includes('label'))
  const locked = isLocked(field)
  const card = el(`<div class="yw-fb__card${field.id === selectedId ? ' yw-fb__card--selected' : ''}${locked ? ' yw-fb__card--locked' : ''}" data-fb-id="${field.id}">
    <div class="yw-fb__card-header">
      <span class="yw-fb__card-drag" title="⇕">⠿</span>
      <span class="yw-fb__card-icon">${icon}</span>
      <span class="yw-fb__card-title">${showLabel ? esc(field.data.label || '') : esc((config.field || config.set || {}).label || field.type)}</span>
      ${field.data.name ? `<code class="yw-fb__card-name">${esc(field.data.name)}</code>` : ''}
      ${field.data.required === '1' ? '<span class="yw-fb__card-required">*</span>' : ''}
      ${locked ? `<span class="yw-fb__card-locked" title="${esc(_t('FORM_BUILDER_LOCKED_HINT'))}"><svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#lock"/></svg>${esc(_t('FORM_BUILDER_LOCKED'))}</span>` : ''}
      <span class="yw-fb__card-actions">
        ${locked ? '' : `<button type="button" class="yw-fb__card-action" data-fb-action="duplicate" title="${_t('FORM_BUILDER_DUPLICATE')}"><svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#copy"/></svg></button>`}
        ${locked ? '' : `<button type="button" class="yw-fb__card-action" data-fb-action="delete" title="${_t('FORM_BUILDER_DELETE')}"><svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#trash"/></svg></button>`}
      </span>
    </div>
    <div class="yw-fb__card-body">
      <div class="yw-fb__card-preview yw-fb__card-preview--pending" id="yw-fb-preview-${field.id}"></div>
    </div>
  </div>`)

  const holder = card.querySelector('.yw-fb__card-preview')
  if (previewHtml[field.id] !== undefined) paintPreview(holder, field)

  card.addEventListener('click', (event) => {
    const action = event.target.closest('[data-fb-action]')?.getAttribute('data-fb-action')
    if (locked && (action === 'delete' || action === 'duplicate')) {
      return
    }
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
  canvasEl.querySelectorAll('.yw-fb__card').forEach((card) => {
    card.classList.toggle('yw-fb__card--selected', card.getAttribute('data-fb-id') === id)
  })
  renderSettings()
  paletteEl.classList.add('hide')
  settingsEl.classList.remove('hide')
}

function closeSettings() {
  selectedId = null
  settingsEl.classList.add('hide')
  paletteEl.classList.remove('hide')
  canvasEl.querySelectorAll('.yw-fb__card--selected').forEach((card) => card.classList.remove('yw-fb__card--selected'))
}

function controlFor(name, def, value) {
  if (def.options && def.multiple) {
    const values = splitCsv(value)
    const group = el(`<div class="yw-fb__checks" data-fb-input="${esc(name)}"></div>`)
    Object.entries(def.options).forEach(([optionValue, optionLabel]) => {
      const checkbox = el(`<label class="yw-fb__check"><input type="checkbox" value="${esc(optionValue)}"${values.includes(optionValue) ? ' checked' : ''}/> ${esc(optionLabel)}</label>`)
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
    const wrap = el(`<div class="yw-fb__file" data-fb-input="${esc(name)}"></div>`)
    const current = String(value ?? '')
    if (current.includes('|data:')) {
      wrap.append(el(`<img class="yw-fb__file-preview" src="${esc(current.split('|')[1])}" alt=""/>`))
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
  const config = configFor(field.type)
  const defs = attributeDefs(config)
  const advanced = config.advancedAttributes || []

  settingsEl.innerHTML = ''
  const title = (config.field || config.set || {}).label || field.type
  settingsEl.append(el(`<div class="yw-fb__settings-header">
    <button type="button" class="yw-btn yw-btn--sm yw-fb__back">← ${_t('FORM_BUILDER_BACK')}</button>
    <h3 class="yw-fb__sidebar-title">${esc(title)}</h3>
  </div>`))
  settingsEl.querySelector('.yw-fb__back').addEventListener('click', closeSettings)

  if (config.editorHint) {
    settingsEl.append(el(`<div class="yw-fb__hint">${config.editorHint}</div>`))
  }

  const main = el('<div class="yw-fb__attributes"></div>')
  const advancedWrap = el(`<details class="yw-fb__advanced"><summary>${_t('FORM_BUILDER_ADVANCED')}</summary></details>`)
  const changeCallbacks = {}

  Object.entries(defs).forEach(([name, def]) => {
    if (name === 'separator') {
      main.append(el('<hr class="yw-fb__separator"/>'))
      return
    }
    const row = el(`<div class="yw-fb__attribute" data-fb-attribute="${esc(name)}">
      <label class="yw-form-label">${esc(def.label ?? name)}</label>
    </div>`)
    row.append(controlFor(name, def, field.data[name] ?? def.value ?? ''))
    if (def.description) row.append(el(`<small class="yw-fb__description">${esc(def.description)}</small>`));
    (advanced.includes(name) ? advancedWrap : main).append(row)
  })

  settingsEl.append(main)
  if (advancedWrap.childElementCount > 1) settingsEl.append(advancedWrap)

  // ------------------------------------------------ editorSetup api
  const readControl = (name) => {
    const control = settingsEl.querySelector(`[data-fb-input="${name}"]`)
    if (!control) return undefined
    if (control.classList.contains('yw-fb__checks')) {
      return Array.from(control.querySelectorAll('input:checked')).map((box) => box.value)
    }
    if (control.classList.contains('yw-fb__file')) return field.data[name] ?? ''
    return control.value
  }

  const writeControl = (name, value) => {
    const control = settingsEl.querySelector(`[data-fb-input="${name}"]`)
    if (!control) return
    if (control.classList.contains('yw-fb__checks')) {
      const values = splitCsv(value)
      control.querySelectorAll('input').forEach((box) => {
        box.checked = values.includes(box.value) // eslint-disable-line no-param-reassign
      })
      return
    }
    if (control.classList.contains('yw-fb__file')) return
    control.value = String(value ?? '')
  }

  const commit = (name, value) => {
    const serialized = joinCsv(value)
    if (serialized === '') delete field.data[name]
    else field.data[name] = serialized
    syncToTextarea()
    refreshCard(field)
    schedulePreviews([field.id])
  }

  const api = {
    getValue: (name) => {
      const def = defs[name]
      if (def?.multiple) {
        const raw = readControl(name) ?? field.data[name] ?? ''
        return splitCsv(raw)
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
    if (control.classList.contains('yw-fb__file')) {
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
    control.addEventListener(control.matches('select, .yw-fb__checks') ? 'change' : 'input', handler)
    if (control.classList.contains('yw-fb__checks')) {
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
