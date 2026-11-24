/* The component rail beside an editor: the server draws the palette and each component's settings, and answers every change with the tag those settings write; this moves that tag into the editor. */
import { claimRailSlot, registerRail } from './editor-rails.js'
import { openIconPicker, iconHtml, iconFromString } from './icon-picker.js'
import FilePickerPanel from './file-picker-panel.js'

const ACTIONS_BACKWARD_COMPATIBILITY = {
  calendar: 'bazarcalendar',
  map: 'entrymap',
}

const builderData = () =>
  typeof actionsBuilderData === 'object' && actionsBuilderData !== null
    ? actionsBuilderData
    : { components: {} }

const panel = () => document.getElementById('actions-builder-panel')
const settingsForm = () => panel()?.querySelector('[data-ab-settings]')

let filePicker = null
const lists = {}

/** The one rail of the page, whichever editor opened it last. */
const rail = {
  isOpen: false,
  editor: null,
  target: null,
  insertAt: null,
  openKey: null,
  editing: false,
  written: null,
  session: null,
  loaded: false,

  get isPlacingNewAction() {
    return this.isOpen && !this.editing
  },

  open(editor, options = {}) {
    const element = panel()
    if (!element) return
    this.editor = editor
    this.target = options.target || null
    this.insertAt = options.insertAt || null
    if (this.isOpen && options.key && options.key === this.openKey) return
    this.openKey = options.key || null
    if (!options.action) editor.clearHighlight()
    registerRail(this)
    claimRailSlot(this)
    element.hidden = false
    this.isOpen = true
    this.editing = Boolean(options.action)
    this.written = null
    if (options.action) {
      const [name] = options.action.split(' ')
      const renamed = ACTIONS_BACKWARD_COMPATIBILITY[name] || name
      this.load({
        action: options.action.replace(new RegExp(`^${name}`), renamed),
      })
    } else {
      this.showPalette()
    }
  },

  close() {
    if (!this.isOpen) return
    const form = settingsForm()?.closest('form')
    if (form) window.htmx?.trigger(form, 'htmx:abort')
    this.editor?.clearHighlight()
    const element = panel()
    if (element) element.hidden = true
    this.isOpen = false
    this.openKey = null
    this.written = null
    this.session = null
  },

  setTitle(text) {
    const title = panel()?.querySelector('[data-ab-title]')
    if (title) title.textContent = text
  },

  showPalette() {
    const element = panel()
    const palette = element.querySelector('[data-ab-palette]')
    element.querySelector('[data-ab-settings-host]').hidden = true
    element.querySelector('[data-ab-settings-host]').replaceChildren()
    palette.hidden = false
    this.session = null
    this.setTitle(element.dataset.abPaletteTitle)
    window.htmx.ajax('GET', element.dataset.abPaletteUrl, {
      source: palette,
      target: palette,
      swap: 'innerHTML',
    })
  },

  load(params) {
    const element = panel()
    const host = element.querySelector('[data-ab-settings-host]')
    element.querySelector('[data-ab-palette]').hidden = true
    element.querySelector('[data-ab-palette]').replaceChildren()
    host.hidden = false
    this.session = Math.random().toString(36).slice(2, 10)
    this.loaded = false
    window.htmx.ajax('GET', element.dataset.abSettingsUrl, {
      source: host,
      target: host,
      swap: 'innerHTML',
      values: {
        ...params,
        session: this.session,
        preview: this.editor?.previewComponent ? '0' : '1',
      },
    })
  },

  received(form) {
    if (!this.isOpen || form.dataset.abSession !== this.session) return
    this.setTitle(form.dataset.abTitle)
    const code = form.dataset.abCode
    if (!this.loaded) {
      this.loaded = true
      if (this.editing) {
        this.written = code
        return
      }
    }
    if (code === this.written) return
    if (this.editing) {
      if (!this.target) return
      const moved = this.editor.replaceRange(this.target, code)
      if (moved) {
        this.target = moved
        this.editor.highlightRange?.(moved)
      }
    } else {
      this.editing = true
      this.openKey = null
      this.target = this.editor.insertAt(this.insertAt, code) || null
      form.querySelector('input[name="editing"]').value = '1'
    }
    this.written = code
  },
}

const changed = (input) =>
  input.dispatchEvent(new Event('change', { bubbles: true }))

document.addEventListener('htmx:afterSettle', (event) => {
  const element = panel()
  if (!element || !element.contains(event.detail.elt)) return
  const form = settingsForm()
  if (form) rail.received(form)
})

document.addEventListener('click', (event) => {
  const element = panel()
  if (!element || !element.contains(event.target)) return
  const component = event.target.closest('[data-ab-component]')
  if (component) {
    rail.editing = false
    rail.written = null
    rail.load({ component: component.dataset.abComponent })
    return
  }
  if (event.target.closest('[data-ab-close]')) {
    rail.close()
    return
  }
  const icon = event.target.closest('[data-ab-icon]')
  if (icon) {
    const value = icon.parentElement.querySelector('[data-ab-icon-value]')
    openIconPicker({
      anchor: icon,
      value: iconFromString(value.value),
      sources: JSON.parse(icon.dataset.abIcon || '["sprite"]'),
      onPick: (picked) => {
        value.value = picked.value
        icon.querySelector('.yw-icon-field__preview').innerHTML =
          iconHtml(picked)
        changed(value)
      },
    })
    return
  }
  const image = event.target.closest(
    '[data-ab-image-choose], [data-ab-image-clear]',
  )
  if (image) {
    const value = image
      .closest('.yw-form-group')
      .querySelector('[data-ab-image-value]')
    if (image.matches('[data-ab-image-clear]')) {
      value.value = ''
      changed(value)
      return
    }
    filePicker ??= new FilePickerPanel()
    filePicker.open({
      only: 'image',
      onPick: ({ tag }) => {
        value.value = tag
        changed(value)
      },
    })
  }
})

document.addEventListener('input', (event) => {
  const element = panel()
  if (!element || !element.contains(event.target)) return
  const synced = event.target.dataset.abSync
  if (synced) document.getElementById(synced).value = event.target.value
})

ywInitEach('[data-ab-autocomplete]', (input) => {
  const kind = input.dataset.abAutocomplete
  lists[kind] ??= fetch(
    wiki.url(kind === 'pages' ? '?api/pages&fields=tag' : '?api/entries'),
  )
    .then((response) => (response.ok ? response.json() : {}))
    .then((data) =>
      Object.values(data || {})
        .map((item) => item.tag)
        .filter(Boolean),
    )
    .catch(() => [])
  lists[kind].then((tags) =>
    window.ywAutocomplete(input, {
      items: 5,
      source: (query) =>
        tags.filter((tag) => tag.toLowerCase().includes(query.toLowerCase())),
      onSelect: (item) => {
        input.value = item
        input.dispatchEvent(new Event('input', { bubbles: true }))
      },
    }),
  )
})

ywInitEach('[data-ab-geo]', (holder) => {
  const lat = holder.querySelector('[data-ab-geo-lat]')
  const lon = holder.querySelector('[data-ab-geo-lon]')
  const zoom = holder.querySelector('[data-ab-geo-zoom]')
  const map = L.map(holder.querySelector('[data-ab-geo-map]'), {
    center: new L.LatLng(Number(lat.value), Number(lon.value)),
    zoom: Number(zoom.value),
    scrollWheelZoom: false,
    layers: [
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution:
          '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      }),
    ],
  })
  map.on('moveend', () => {
    lat.value = map.getCenter().lat.toFixed(5)
    lon.value = map.getCenter().lng.toFixed(5)
    zoom.value = map.getZoom()
    changed(zoom)
  })
})

/** What the editors are handed: the rail, and what it knows of the components a page may hold. */
export default class {
  get allAvailableActions() {
    return [
      ...new Set(
        Object.values(builderData().components || {}).flatMap(
          (component) => component.tags || [],
        ),
      ),
    ]
  }

  get allAvailableActionsWithBackward() {
    return this.allAvailableActions.concat(
      Object.keys(ACTIONS_BACKWARD_COMPATIBILITY),
    )
  }

  getActionConfiguration(actionName) {
    const components = Object.values(builderData().components || {})

    return (
      components.find((c) => (c.tags || []).includes(actionName) && !c.pins) ||
      components.find((c) => (c.tags || []).includes(actionName)) ||
      {}
    )
  }

  get isPlacingNewAction() {
    return rail.isPlacingNewAction
  }

  close() {
    rail.close()
  }

  open(editor, options) {
    rail.open(editor, options)
  }
}
