import registry, { paletteEntries } from './registry.js'
import { parseFields, resolveWikiType, serializeFields } from './converter.js'
import { refreshDesignerData } from './fields/commons/attributes.js'

let container
let textarea
let lockedFieldNames = []

function readLockedFieldNames() {
  try {
    const declared = JSON.parse(container?.dataset.lockedFields || '[]')
    return Array.isArray(declared) ? declared : []
  } catch {
    return []
  }
}

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
    : String(value ?? '')
        .split(',')
        .map((part) => part.trim())
        .filter(Boolean)
}

function joinCsv(value) {
  return Array.isArray(value) ? value.join(',') : String(value ?? '')
}

function configFor(type) {
  return registry[type] || registry.custom
}

let fields = []
let selectedId = null
let uid = 0

function newId() {
  uid += 1
  return `fbf${uid}`
}

function selectedField() {
  return fields.find((field) => field.id === selectedId) || null
}

function baseAttributes() {
  return {
    label: { label: _t('FORM_BUILDER_LABEL_LABEL'), value: '' },
    name: { label: _t('FORM_BUILDER_NAME_LABEL'), value: '' },
    default: { label: _t('FORM_BUILDER_DEFAULT_LABEL'), value: '' },
    required: {
      label: _t('FORM_BUILDER_REQUIRED_LABEL'),
      options: { '': _t('NO'), 1: _t('YES') },
    },
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
      ;[data[name]] = Object.keys(def.options)
    }
  })
  Object.assign(data, extraData)
  if (
    !data.label &&
    config.field?.label &&
    !(config.disabledAttributes || []).includes('label')
  ) {
    data.label = config.field.label
  }
  if (!data.name && !(config.disabledAttributes || []).includes('name')) {
    data.name = uniqueName(
      config.defaultIdentifier || `bf_${type.replace(/[^a-z0-9]/gi, '')}`,
    )
  }
  return { id: newId(), type, data }
}

function serialize() {
  return serializeFields(
    fields.map(({ type, data }) => ({ type, data })),
    registry,
  )
}

function syncToTextarea() {
  textarea.value = serialize()
  updateTitleSelect()
  updateRoleSelects()
}

const CUSTOM_TITLE = '__custom__'
let titleSelect
let titleCustom

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

function updateRoleSelects() {
  document.querySelectorAll('[data-yw-field-role]').forEach((selectParam) => {
    const select = selectParam
    const types = (select.dataset.ywRoleTypes || '')
      .split(',')
      .filter((t) => t.length > 0)
    const chosen = select.value || select.dataset.ywRoleCurrent || ''
    select.innerHTML = ''

    const auto = document.createElement('option')
    auto.value = ''
    auto.textContent = _t('FORM_EDIT_FIELD_ROLE_AUTOMATIC')
    select.append(auto)

    fields.forEach(({ type, data }) => {
      const wikiType = resolveWikiType(type, data)
      if (!data.name || (types.length > 0 && !types.includes(wikiType))) return
      const option = document.createElement('option')
      option.value = data.name
      option.textContent = data.label
        ? `${data.label} (${data.name})`
        : data.name
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

let paletteEl
let settingsEl
let canvasEl
let errorEl
let railEl
let railTitleEl
let railBackEl
let railWanted = true

function boot(root) {
  container = root
  textarea = document.getElementById('form-builder-text')
  if (!textarea) return
  titleSelect = document.getElementById('entry-title-select')
  titleCustom = document.getElementById('entry-title-custom')
  lockedFieldNames = readLockedFieldNames()
  refreshDesignerData(container)
  document.getElementById('yw-fb-rail')?.remove()
  container.classList.add('yw-fb')
  container.innerHTML = ''
  errorEl = el('<div class="yw-fb__error hide"></div>')
  paletteEl = el(`<div class="yw-fb__palette">
    <div class="yw-fb__palette-grid"></div>
  </div>`)
  settingsEl = el('<div class="yw-fb__settings hide"></div>')
  canvasEl = el('<div class="yw-fb__canvas"></div>')
  railEl = el(`<aside class="yw-designer__sidebar yw-fb__rail" id="yw-fb-rail">
    <div class="yw-rail__header">
      <button type="button" class="yw-btn yw-btn--sm yw-rail__back yw-fb__back hide"
        aria-label="${esc(_t('FORM_BUILDER_BACK'))}"><svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#chevron-left"/></svg></button>
      <h2 class="yw-rail__title">${esc(_t('FORM_BUILDER_ADD_FIELDS'))}</h2>
      <button type="button" class="yw-close" data-fb-close-rail aria-label="close">&times;</button>
    </div>
  </aside>`)
  railTitleEl = railEl.querySelector('.yw-rail__title')
  railBackEl = railEl.querySelector('.yw-fb__back')
  railEl.append(paletteEl, settingsEl)
  container.append(errorEl, canvasEl)
  document.body.append(railEl)

  renderPalette()
  if (!loadFromTextarea()) {
    showError(`${_t('FORM_BUILDER_INVALID_JSON')}`)
    fields = []
  }
  renderCanvas()
  initCanvasSort()
  bindTabsAndSubmit()
  bindTitleSelect()
  updateRoleSelects()
  initRail()
  initPresentation()
  initEntryTemplate()
}

/** The squelette, style and preset selects follow the theme select. */
function initPresentation() {
  const block = document.querySelector('[data-yw-presentation]')
  const themeSelect = block?.querySelector('[data-yw-presentation-theme]')
  if (!block || !themeSelect) return
  let themes = {}
  let customPresets = {}
  try {
    themes = JSON.parse(block.dataset.themes || '{}')
    customPresets = JSON.parse(block.dataset.customPresets || '{}')
  } catch {
    return
  }
  const wikiTheme =
    themeSelect.options[0]?.textContent.match(/\(([^)]*)\)/)?.[1]
  themeSelect.addEventListener('change', () => {
    const theme = themes[themeSelect.value || wikiTheme] || {}
    block.querySelectorAll('[data-yw-presentation-part]').forEach((select) => {
      const part = select.dataset.ywPresentationPart
      const wanted = select.value
      const files = {
        ...(theme[part] || {}),
        ...(part === 'presets' ? customPresets : {}),
      }
      Array.from(select.options)
        .slice(1)
        .forEach((option) => option.remove())
      Object.entries(files).forEach(([file, label]) => {
        select.append(new Option(label, file, false, file === wanted))
      })
    })
  })
}

/** The entry template editor: Ace, fetched once the section opens, plus a starter naming every field. */
function initEntryTemplate() {
  const source = document.querySelector('[data-yw-twig-editor]')
  if (!source) return
  let editor = null

  const setText = (text) => {
    if (editor) {
      editor.setValue(text)
      editor.ace.clearSelection()
    } else {
      source.value = text
    }
  }

  const mount = async () => {
    if (editor || source.readOnly) return
    const { default: AceWrapper } = await import('../ace-wrapper.js')
    if (editor) return
    const host = document.createElement('div')
    host.className = 'yw-templates__ace'
    source.parentNode.insertBefore(host, source)
    source.hidden = true
    editor = new AceWrapper(host, { mode: 'ace/mode/twig', rows: 14 })
    editor.setValue(source.value)
    editor.ace.clearSelection()
    editor.ace.moveCursorTo(0, 0)
    editor.disableAutocompletion()
    editor.on('change', () => {
      source.value = editor.getValue()
    })
  }

  const section = source.closest('details')
  if (!section || section.open) {
    mount()
  } else {
    section.addEventListener('toggle', () => section.open && mount(), {
      once: true,
    })
  }
  source.form?.addEventListener('submit', () => {
    if (editor) source.value = editor.getValue()
  })

  document
    .querySelector('[data-yw-fb-template-starter]')
    ?.addEventListener('click', () => {
      const named = fields.filter(({ data }) => data.name)
      const lines = [
        `{# ${_t('FORM_EDIT_ENTRY_TEMPLATE_STARTER_COMMENT')} #}`,
        '<div class="BAZ_fiche_custom">',
        ...named.map(({ data }, index) =>
          index === 0
            ? `  <h1 class="BAZ_fiche_titre">{{ html.${data.name}|raw }}</h1>`
            : `  <div class="BAZ_texte yw-field-${data.name}">{{ html.${data.name}|raw }}</div>`,
        ),
        '</div>',
        '',
      ]
      setText(lines.join('\n'))
    })
}

function showError(message) {
  errorEl.textContent = message
  errorEl.classList.remove('hide')
}

function clearError() {
  errorEl.classList.add('hide')
}

function normalizeForFilter(text) {
  return String(text).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
}

function renderPalette() {
  const filter = el(
    `<input type="search" class="yw-input yw-fb__filter" placeholder="${esc(_t('FORM_BUILDER_FILTER'))}"/>`,
  )
  paletteEl.insertBefore(
    filter,
    paletteEl.querySelector('.yw-fb__palette-grid'),
  )

  const grid = paletteEl.querySelector('.yw-fb__palette-grid')
  paletteEntries.forEach(({ type, config }) => {
    const entry = config.set || config.field
    const item =
      el(`<button type="button" class="yw-fb__palette-item" data-fb-type="${esc(type)}">
      <span class="yw-fb__palette-icon">${(config.field || config.set).icon || ''}</span>
      <span class="yw-fb__palette-label">${esc(entry.label)}</span>
    </button>`)
    item.addEventListener('click', () => addFromPalette(type))
    grid.append(item)
  })

  filter.addEventListener('input', () => {
    const needle = normalizeForFilter(filter.value.trim())
    grid.querySelectorAll('.yw-fb__palette-item').forEach((item) => {
      const label = item.querySelector('.yw-fb__palette-label').textContent
      item.classList.toggle(
        'hide',
        needle !== '' && !normalizeForFilter(label).includes(needle),
      )
    })
  })

  if (window.Sortable) {
    window.Sortable.create(grid, {
      group: { name: 'fb', pull: 'clone', put: false },
      sort: false,
      animation: 150,
    })
  }
}

function initRail() {
  railBackEl.addEventListener('click', closeSettings)
  railEl
    .querySelector('[data-fb-close-rail]')
    .addEventListener('click', () => showRail(false))
  document.querySelectorAll('[data-yw-fb-open]').forEach((button) => {
    button.addEventListener('click', () => showRail(true))
  })
}

/**
 * Open or shut the rail, remembering which the author asked for.
 *
 * The code tab hides the rail without going through here: a JSON textarea has no field to
 * add to, but the author never asked for the drawer to be gone, so switching back reopens it.
 */
function showRail(open) {
  railWanted = open
  railEl.hidden = !open
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

const PREVIEW_DEBOUNCE = 300

const previewHtml = {}
const previewRequests = {}
let previewTimer = null
let previewRequestId = 0
let previewPendingAll = false
let previewPendingIds = new Set()

function previewHolder(field) {
  return canvasEl.querySelector(
    `[data-fb-id="${field.id}"] .yw-fb__card-preview`,
  )
}

function neutralizePreview(holder, fieldId) {
  holder
    .querySelectorAll('input, select, textarea, button')
    .forEach((control) => {
      control.disabled = true
      control.removeAttribute('name')
      control.removeAttribute('required')
    })

  const prefix = `${fieldId}__`
  holder.querySelectorAll('[id]').forEach((node) => {
    if (node.id.startsWith(prefix)) return
    node.id = prefix + node.id
  })
  holder.querySelectorAll('[for]').forEach((node) => {
    const target = node.getAttribute('for')
    if (target && !target.startsWith(prefix))
      node.setAttribute('for', prefix + target)
  })
}

function hasVisiblePreview(holder) {
  return (
    holder.textContent.trim() !== '' ||
    holder.querySelector(
      'input:not([type="hidden"]), select, textarea, img, svg, canvas, iframe',
    ) !== null
  )
}

function paintPreview(holder, field) {
  holder.innerHTML = previewHtml[field.id] ?? ''
  neutralizePreview(holder, field.id)
  if (!hasVisiblePreview(holder)) {
    holder.innerHTML = `<em class="yw-fb__card-nopreview">${esc(_t('FORM_BUILDER_NO_PREVIEW'))}</em>`
  }
  holder.classList.remove('yw-fb__card-preview--pending')
  // Painting writes markup nobody announced, and an unannounced node is an uninitialised
  // one: every initialiser hangs off `htmx:load` (yw-init.js). The first preview on a page
  // got away with it because loading leaflet fires `yw:assets-ready`, which re-runs every
  // initialiser over the whole document. The second one does not: its assets are already
  // there, so nothing fires, and the canvas re-render that added it had just replaced the
  // first map's mounted DOM with a fresh copy of the same string. Both maps ended up as
  // markup. `process` wires the hx attributes a preview carries (the tag input has one),
  // `trigger` is how this file says "these nodes are new" in the vocabulary yw-init reads.
  htmx.process(holder)
  htmx.trigger(holder, 'htmx:load')
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

  const template = serializeFields(
    targets.map(({ type, data }) => ({ type, data })),
    registry,
  )
  const ids = JSON.stringify(targets.map((field) => field.id))

  try {
    await htmx.ajax('POST', wiki.url('?api/forms/preview'), {
      source: canvasEl,
      swap: 'none',
      values: { template, ids },
    })
  } catch (error) {
    console.error('form designer: field preview request failed', error)
    targets.forEach((field) => {
      if (previewRequests[field.id] === requestId) {
        previewHolder(field)?.classList.remove('yw-fb__card-preview--pending')
      }
    })
    return
  }

  targets.forEach((field) => {
    const holder = previewHolder(field)
    if (!holder) return
    if (previewRequests[field.id] !== requestId) {
      paintPreview(holder, field)
      return
    }
    previewHtml[field.id] = holder.innerHTML
    paintPreview(holder, field)
  })
}

function initCanvasSort() {
  if (!window.Sortable) return
  window.Sortable.create(canvasEl, {
    group: 'fb',
    handle: '.yw-fb__card-drag',
    animation: 150,
    onAdd(event) {
      const type = event.item.getAttribute('data-fb-type')
      event.item.remove()
      addFromPalette(type, event.newIndex)
    },
    onUpdate(event) {
      const [moved] = fields.splice(event.oldIndex, 1)
      fields.splice(event.newIndex, 0, moved)
      syncToTextarea()
    },
  })
}

function renderCanvas() {
  canvasEl.innerHTML = ''
  if (fields.length === 0) {
    canvasEl.append(
      el(
        `<div class="yw-fb__empty" data-fb-keep>${_t('FORM_BUILDER_EMPTY')}</div>`,
      ),
    )
  }
  fields.forEach((field) => {
    canvasEl.append(renderCard(field))
    paintCached(field)
  })
  forgetStalePreviews()
  const missing = fields
    .filter((field) => previewHtml[field.id] === undefined)
    .map((field) => field.id)
  if (missing.length > 0) schedulePreviews(missing)
}

function renderCard(field) {
  const config = configFor(field.type)
  const icon = (config.field || config.set || {}).icon || ''
  const showLabel = !(config.disabledAttributes || []).includes('label')
  const locked = isLocked(field)
  const card =
    el(`<div class="yw-fb__card${field.id === selectedId ? ' yw-fb__card--selected' : ''}${locked ? ' yw-fb__card--locked' : ''}" data-fb-id="${field.id}">
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

  card.addEventListener('click', (event) => {
    const action = event.target
      .closest('[data-fb-action]')
      ?.getAttribute('data-fb-action')
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
  if (!existing) return
  existing.replaceWith(renderCard(field))
  paintCached(field)
}

/**
 * Paint a card's cached preview, once the card is in the document.
 *
 * Order matters and is the whole reason this is its own function: painting announces the
 * nodes it wrote by firing `htmx:load`, that event bubbles to the listener on `document`,
 * and a card that has not been appended yet bubbles to nobody. Painting inside `renderCard`
 * meant every re-rendered preview was left as inert markup.
 */
function paintCached(field) {
  if (previewHtml[field.id] === undefined) return
  const holder = previewHolder(field)
  if (holder) paintPreview(holder, field)
}

function selectField(id) {
  selectedId = id
  canvasEl.querySelectorAll('.yw-fb__card').forEach((card) => {
    card.classList.toggle(
      'yw-fb__card--selected',
      card.getAttribute('data-fb-id') === id,
    )
  })
  renderSettings()
  paletteEl.classList.add('hide')
  settingsEl.classList.remove('hide')
  railBackEl.classList.remove('hide')
  showRail(true)
  railEl.scrollTop = 0
}

function closeSettings() {
  selectedId = null
  settingsEl.classList.add('hide')
  paletteEl.classList.remove('hide')
  railBackEl.classList.add('hide')
  railTitleEl.textContent = _t('FORM_BUILDER_ADD_FIELDS')
  canvasEl
    .querySelectorAll('.yw-fb__card--selected')
    .forEach((card) => card.classList.remove('yw-fb__card--selected'))
}

function controlFor(name, def, value) {
  if (def.options && def.multiple) {
    const values = splitCsv(value)
    const group = el(
      `<div class="yw-fb__checks" data-fb-input="${esc(name)}"></div>`,
    )
    Object.entries(def.options).forEach(([optionValue, optionLabel]) => {
      const checkbox = el(
        `<label class="yw-fb__check"><input type="checkbox" value="${esc(optionValue)}"${values.includes(optionValue) ? ' checked' : ''}/> ${esc(optionLabel)}</label>`,
      )
      group.append(checkbox)
    })
    return group
  }
  if (def.options) {
    const select = el(
      `<select class="yw-input" data-fb-input="${esc(name)}"></select>`,
    )
    Object.entries(def.options).forEach(([optionValue, optionLabel]) => {
      const option = el(
        `<option value="${esc(optionValue)}">${esc(optionLabel)}</option>`,
      )
      if (String(value ?? '') === String(optionValue)) option.selected = true
      select.append(option)
    })
    return select
  }
  if (def.type === 'textarea') {
    const area = el(
      `<textarea class="yw-input" rows="${esc(def.rows || 4)}" data-fb-input="${esc(name)}"></textarea>`,
    )
    area.value = String(value ?? '')
    return area
  }
  if (def.type === 'file') {
    const wrap = el(
      `<div class="yw-fb__file" data-fb-input="${esc(name)}"></div>`,
    )
    const current = String(value ?? '')
    if (current.includes('|data:')) {
      wrap.append(
        el(
          `<img class="yw-fb__file-preview" src="${esc(current.split('|')[1])}" alt=""/>`,
        ),
      )
    }
    wrap.append(
      el(
        `<input type="file"${def.accept ? ` accept="${esc(def.accept)}"` : ''}/>`,
      ),
    )
    return wrap
  }
  const input = el(
    `<input class="yw-input" type="${def.type === 'number' ? 'number' : 'text'}" data-fb-input="${esc(name)}"${def.placeholder ? ` placeholder="${esc(def.placeholder)}"` : ''}/>`,
  )
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
  railTitleEl.textContent =
    (config.field || config.set || {}).label || field.type

  if (config.editorHint) {
    settingsEl.append(el(`<div class="yw-fb__hint">${config.editorHint}</div>`))
  }

  const main = el('<div class="yw-fb__attributes"></div>')
  const advancedWrap = el(
    `<details class="yw-fb__advanced"><summary>${_t('FORM_BUILDER_ADVANCED')}</summary></details>`,
  )
  const changeCallbacks = {}

  Object.entries(defs).forEach(([name, def]) => {
    if (name === 'separator') {
      main.append(el('<hr class="yw-fb__separator"/>'))
      return
    }
    const row =
      el(`<div class="yw-fb__attribute" data-fb-attribute="${esc(name)}">
      <label class="yw-form-label">${esc(def.label ?? name)}</label>
    </div>`)
    row.append(controlFor(name, def, field.data[name] ?? def.value ?? ''))
    if (def.description)
      row.append(
        el(`<small class="yw-fb__description">${esc(def.description)}</small>`),
      )
    ;(advanced.includes(name) ? advancedWrap : main).append(row)
  })

  settingsEl.append(main)
  if (advancedWrap.childElementCount > 1) settingsEl.append(advancedWrap)

  const readControl = (name) => {
    const control = settingsEl.querySelector(`[data-fb-input="${name}"]`)
    if (!control) return undefined
    if (control.classList.contains('yw-fb__checks')) {
      return Array.from(control.querySelectorAll('input:checked')).map(
        (box) => box.value,
      )
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
        box.checked = values.includes(box.value)
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
      if (!silent)
        (changeCallbacks[name] || []).forEach((callback) => callback())
    },
    show: (name) =>
      settingsEl
        .querySelector(`[data-fb-attribute="${name}"]`)
        ?.classList.remove('hide'),
    hide: (name) =>
      settingsEl
        .querySelector(`[data-fb-attribute="${name}"]`)
        ?.classList.add('hide'),
    setLabel: (name, text) => {
      const label = settingsEl.querySelector(
        `[data-fb-attribute="${name}"] > label`,
      )
      if (label) label.textContent = text
    },
    setOptions: (name, options) => {
      const control = settingsEl.querySelector(
        `select[data-fb-input="${name}"]`,
      )
      if (!control) return
      const current = control.value || String(field.data[name] ?? '')
      const entries = Object.entries(options)
      if (
        current &&
        !entries.some(([optionValue]) => optionValue === current)
      ) {
        entries.unshift([current, current])
      }
      control.innerHTML = ''
      entries.forEach(([optionValue, optionLabel]) => {
        const option = el(
          `<option value="${esc(optionValue)}">${esc(optionLabel)}</option>`,
        )
        if (current === String(optionValue)) option.selected = true
        control.append(option)
      })
    },
    getRow: (name) => settingsEl.querySelector(`[data-fb-attribute="${name}"]`),
    onChange: (name, callback) => {
      ;(changeCallbacks[name] = changeCallbacks[name] || []).push(callback)
    },
  }

  settingsEl.querySelectorAll('[data-fb-input]').forEach((control) => {
    const name = control.getAttribute('data-fb-input')
    if (control.classList.contains('yw-fb__file')) {
      control
        .querySelector('input[type=file]')
        .addEventListener('change', (event) => {
          const file = event.target.files[0]
          if (!file) {
            commit(name, '')
            return
          }
          const reader = new FileReader()
          reader.onload = () => {
            commit(name, `${file.name}|${reader.result}`)
            ;(changeCallbacks[name] || []).forEach((callback) => callback())
          }
          reader.readAsDataURL(file)
        })
      return
    }
    const handler = () => {
      commit(name, readControl(name))
      ;(changeCallbacks[name] || []).forEach((callback) => callback())
    }
    control.addEventListener(
      control.matches('select, .yw-fb__checks') ? 'change' : 'input',
      handler,
    )
    if (control.classList.contains('yw-fb__checks')) {
      control
        .querySelectorAll('input')
        .forEach((box) => box.addEventListener('change', handler))
    }
  })

  config.editorSetup?.(api)
}

function bindTabsAndSubmit() {
  document.querySelectorAll('a[href="#formbuilder"]').forEach((link) => {
    link.addEventListener('click', () => {
      if (
        textarea.value.trim() === '' ||
        textarea.value.trim() === serialize().trim()
      )
        return
      if (loadFromTextarea()) {
        clearError()
        closeSettings()
        renderCanvas()
      } else {
        showError(`${_t('FORM_BUILDER_INVALID_JSON')}`)
      }
    })
    link.addEventListener('click', () => {
      railEl.hidden = !railWanted
    })
  })
  document.querySelectorAll('a[href="#code"]').forEach((link) => {
    link.addEventListener('click', () => {
      syncToTextarea()
      railEl.hidden = true
    })
  })
  textarea.form?.addEventListener('submit', () => {
    const designerPane = document.getElementById('formbuilder')
    if (designerPane?.classList.contains('active')) syncToTextarea()
  })
}

ywInitEach('#form-builder-container', boot)
