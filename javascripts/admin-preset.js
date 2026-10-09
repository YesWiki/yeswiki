const palettes = []

const livePalettes = () => palettes.filter(({ box }) => box.isConnected)

document.addEventListener('click', (event) => {
  livePalettes().forEach(({ box, close }) => {
    if (!box.hidden && !box.contains(event.target)) close()
  })
})
document.addEventListener('keydown', (event) => {
  if (event.key !== 'Escape') return
  livePalettes().forEach(({ box, close }) => {
    if (!box.hidden) close()
  })
})

/** The preset rail, re-bound on every htmx swap -- see the note in admin-files.js. */
ywInitEach('#yw-preset-rail', (rail) => {
  const form = rail.querySelector('form')
  const screens = [...rail.querySelectorAll('[data-yw-preset-screen]')]
  const schemeBlocks = [...rail.querySelectorAll('[data-scheme]')]
  const schemeNote = rail.querySelector('[data-yw-preset-scheme-note]')
  const title = rail.querySelector('[data-yw-preset-rail-title]')
  const idField = rail.querySelector('[data-yw-preset-id]')
  const nameField = rail.querySelector('#yw-preset-name')
  const fields = [...rail.querySelectorAll('[data-yw-preset-field]')]

  const wikiPreset = document.getElementById('wikipreset')

  let tryLink = null

  /** Wear a preset on this page alone. */
  function tryOn(href, id) {
    if (wikiPreset) wikiPreset.disabled = true
    tryLink?.remove()
    tryLink = null

    if (href) {
      tryLink = document.createElement('link')
      tryLink.rel = 'stylesheet'
      tryLink.href = href
      document.head.appendChild(tryLink)
    }

    document.querySelectorAll('[data-yw-preset-card]').forEach((element) => {
      element.classList.toggle(
        'yw-preset-card--trying',
        element.dataset.ywPresetCard === id,
      )
    })
  }

  document.querySelectorAll('[data-yw-preset-try]').forEach((button) => {
    button.addEventListener('click', () => {
      tryOn(
        button.dataset.presetHref,
        button.closest('[data-yw-preset-card]').dataset.ywPresetCard,
      )
    })
  })

  const beforePreview = new Map()

  /** Which Colour scheme this page is being read in. */
  function currentScheme() {
    if (document.documentElement.dataset.theme) {
      return document.documentElement.dataset.theme
    }
    return window.matchMedia('(prefers-color-scheme: dark)').matches
      ? 'dark'
      : 'light'
  }

  /** `light.yw-primary` -> ['light', 'yw-primary']. */
  function partsOf(name) {
    const separator = name.indexOf('.')
    return [name.slice(0, separator), name.slice(separator + 1)]
  }

  /** Is this token one value for both schemes -- a measure, a font, `--yw-text-on-dark`? */
  function isSchemeIndependent(token) {
    return !rail.querySelector(`[data-yw-preset-field="dark.${token}"]`)
  }

  const gallery = document.querySelector('.yw-preset-preview')
  const pageFontSize = parseFloat(
    getComputedStyle(document.documentElement).getPropertyValue(
      '--yw-font-size-base',
    ),
  )

  /** Text size and spacing stay on the gallery, so the rail keeps its own measures. */
  function galleryOnly(token) {
    return Boolean(gallery) && /^yw-(font-size-base|space-)/.test(token)
  }

  /** Where a token is painted, and the style property that carries it there. */
  function placeOf(token) {
    if (!galleryOnly(token)) return [document.documentElement, `--${token}`]
    return token === 'yw-font-size-base'
      ? [gallery, 'zoom']
      : [gallery, `--${token}`]
  }

  /** Text size as a zoom on the gallery: the page's rem, and with it the rail, stays put. */
  function styleValueOf(token, value) {
    if (token !== 'yw-font-size-base' || !galleryOnly(token)) return value
    const size = parseFloat(value)
    return size > 0 && pageFontSize > 0 ? String(size / pageFontSize) : ''
  }

  /** Paint one token onto the document, so the gallery below repaints with it. */
  function preview(name, value) {
    const [scheme, token] = partsOf(name)
    if (scheme !== currentScheme() && !isSchemeIndependent(token)) return
    const [element, property] = placeOf(token)
    if (!beforePreview.has(token)) {
      beforePreview.set(token, element.style.getPropertyValue(property))
    }
    element.style.setProperty(property, styleValueOf(token, value))
  }

  function undoPreview() {
    undoInks()
    beforePreview.forEach((value, token) => {
      const [element, property] = placeOf(token)
      if (value) element.style.setProperty(property, value)
      else element.style.removeProperty(property)
    })
    beforePreview.clear()
  }

  /** The value a field opens on: `{ light: {...}, dark: {...} }`, addressed by its name. */
  function valueOf(values, name) {
    const [scheme, token] = partsOf(name)
    return values[scheme]?.[token] ?? ''
  }

  /** The picker beside a text field, where the field is a colour. */
  function pickerFor(name) {
    return rail.querySelector(`[data-yw-preset-picker="${name}"]`)
  }

  /** The slider that drives a field, where the field is a measure. */
  function sliderFor(name) {
    return rail.querySelector(`[data-yw-preset-slider="${name}"]`)
  }

  /** The readout beside that slider: what the number it is on actually means. */
  function readoutFor(name) {
    return rail.querySelector(`[data-yw-preset-readout="${name}"]`)
  }

  /** Where the slider sits for a value it may not be able to express. */
  function asSliderValue(slider, value) {
    const min = Number(slider.min)
    const max = Number(slider.max)
    const step = Number(slider.step) || 1
    const size = parseFloat(String(value ?? '').trim())
    if (!Number.isFinite(size))
      return slider.defaultValue || String((min + max) / 2)
    const snapped = Math.round((size - min) / step) * step + min
    return String(Number(Math.min(max, Math.max(min, snapped)).toFixed(4)))
  }

  /** The value a slider posts: its number with the token's own unit put back on. */
  function measureOf(slider) {
    return `${slider.value}${slider.dataset.unit || ''}`
  }

  /** What the readout says: the number as the thing it measures. */
  function readoutTextFor(slider) {
    const unit = slider.dataset.unit || ''
    if (unit === 'px') return `${slider.value}px`
    return `${slider.value}\u00d7`
  }

  /** Let a font select hold a value that is not one of the offered stacks. */
  function ensureOption(field, value) {
    if (field.tagName !== 'SELECT') return
    if (field.classList.contains('yw-preset-rail__choice')) return
    field.querySelector('[data-yw-preset-own]')?.remove()
    if (value === '' || [...field.options].some((o) => o.value === value))
      return

    const option = document.createElement('option')
    option.value = value
    option.textContent = value
    option.style.fontFamily = value
    option.dataset.ywPresetOwn = ''
    field.insertBefore(option, field.firstChild)
  }

  /** A font select shows its own choice, so the preview is there before the list is opened. */
  function showChosenFont(field) {
    if (field.classList.contains('yw-preset-rail__font')) {
      field.style.fontFamily = field.value
    }
  }

  /** Put the number a slider is on into words beside it. */
  function showMeasure(name, slider) {
    const readout = readoutFor(name)
    if (readout) readout.textContent = readoutTextFor(slider)
  }

  /** A `#rrggbb` the native picker will accept, for anything a colour can be written as. */
  function asHex(value) {
    const trimmed = String(value ?? '').trim()
    if (/^#[0-9a-f]{6}$/i.test(trimmed)) return trimmed
    if (/^#[0-9a-f]{3}$/i.test(trimmed)) {
      return `#${trimmed[1]}${trimmed[1]}${trimmed[2]}${trimmed[2]}${trimmed[3]}${trimmed[3]}`
    }
    const rgb = rgbOf(trimmed)
    if (!rgb) return '#000000'
    return `#${rgb.map((c) => Math.round(c).toString(16).padStart(2, '0')).join('')}`
  }

  const badges = [...rail.querySelectorAll('[data-yw-preset-contrast]')]

  const probe = document.createElement('span')
  probe.setAttribute('aria-hidden', 'true')
  probe.style.display = 'none'
  rail.appendChild(probe)

  function rgbOf(value) {
    probe.style.color = ''
    probe.style.color = String(value ?? '').trim()
    const computed = getComputedStyle(probe).color
    const parts = computed.match(/[\d.]+/g)
    if (!parts || parts.length < 3) return null
    return parts.slice(0, 3).map(Number)
  }

  /** WCAG 2.1 relative luminance: sRGB linearised, then weighted. */
  function luminance([r, g, b]) {
    const channel = (value) => {
      const v = value / 255
      return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4
    }
    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)
  }

  /** The WCAG ratio between two colours: 1 (identical) to 21 (black on white). */
  function contrastRatio(a, b) {
    const first = rgbOf(a)
    const second = rgbOf(b)
    if (!first || !second) return null
    const light = Math.max(luminance(first), luminance(second))
    const dark = Math.min(luminance(first), luminance(second))
    return (light + 0.05) / (dark + 0.05)
  }

  /** The WCAG 2.1 grade a contrast ratio earns. */
  function gradeOf(ratio) {
    if (ratio >= 7) return 'AAA'
    if (ratio >= 4.5) return 'AA'
    if (ratio >= 3) return 'AA-large'
    return 'fail'
  }

  /** The value a name currently holds in the rail, whichever kind of control carries it. */
  function fieldValue(name) {
    return rail.querySelector(`[data-yw-preset-field="${name}"]`)?.value ?? ''
  }

  /** Re-sync every colour swatch from its field. */
  function showPickers() {
    for (const field of fields) {
      const picker = pickerFor(field.dataset.ywPresetField)
      if (picker) picker.value = asHex(field.value)
    }
  }

  const inkFor = JSON.parse(rail.dataset.ywPresetInkFor || '{}')

  const inkPainted = new Set()

  function showInks() {
    const onLight = fieldValue('light.yw-ink-on-light')
    const onDark = fieldValue('light.yw-ink-on-dark')
    const scheme = currentScheme()

    for (const [fill, property] of Object.entries(inkFor)) {
      const colour = fieldValue(`${scheme}.${fill}`)
      const ink =
        (contrastRatio(colour, onLight) ?? 0) >
        (contrastRatio(colour, onDark) ?? 0)
          ? onLight
          : onDark
      document.documentElement.style.setProperty(`--${property}`, ink)
      inkPainted.add(property)
    }
  }

  function undoInks() {
    for (const property of inkPainted) {
      document.documentElement.style.removeProperty(`--${property}`)
    }
    inkPainted.clear()
  }

  /** What a colour is scored against. */
  function groundFor(badge) {
    const [scheme] = partsOf(badge.dataset.ywPresetContrast)
    if (badge.dataset.against !== 'auto-ink')
      return fieldValue(badge.dataset.against)

    const colour = fieldValue(badge.dataset.ywPresetContrast)
    const onLight = fieldValue('light.yw-ink-on-light')
    const onDark = fieldValue('light.yw-ink-on-dark')
    void scheme

    return (contrastRatio(colour, onLight) ?? 0) >
      (contrastRatio(colour, onDark) ?? 0)
      ? onLight
      : onDark
  }

  function showContrast() {
    for (const badge of badges) {
      const ratio = contrastRatio(
        fieldValue(badge.dataset.ywPresetContrast),
        groundFor(badge),
      )
      if (ratio === null) {
        badge.textContent = ''
        badge.removeAttribute('data-grade')
        continue
      }
      const grade = gradeOf(ratio)
      badge.textContent = `${ratio.toFixed(1)} ${grade === 'fail' ? '✕' : grade}`
      badge.dataset.grade = grade
    }
  }

  const fontPicker = rail.querySelector('[data-yw-google-fonts]')
  let cataloguePromise = null

  function loadGoogleFonts() {
    if (cataloguePromise) return cataloguePromise

    cataloguePromise = fetch(fontPicker.dataset.ywGoogleFonts)
      .then((response) => (response.ok ? response.json() : []))
      .then((families) => {
        const options = {}
        for (const family of families) options[family] = family
        fontPicker.dataset.ywTagInputOptions = JSON.stringify(options)
        return options
      })
      .catch(() => ({}))

    return cataloguePromise
  }

  const previewed = new Set()

  /** Ask Google for these families, once each, so this document can draw them. */
  function askGoogleFor(families) {
    const wanted = families.filter((family) => family && !previewed.has(family))
    if (!wanted.length) return
    for (const family of wanted) previewed.add(family)
    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href =
      'https://fonts.googleapis.com/css2?' +
      wanted.map((family) => `family=${encodeURIComponent(family)}`).join('&') +
      '&display=swap'
    document.head.appendChild(link)
  }

  function previewFamilies(families) {
    askGoogleFor(families)

    for (const option of fontPicker.querySelectorAll(
      '[data-yw-tag-input-suggestion]',
    )) {
      option.style.fontFamily = `'${option.dataset.id.replace(/'/g, '')}', sans-serif`
    }
  }

  if (fontPicker) {
    const search = fontPicker.querySelector('[data-yw-tag-input-search]')
    search?.addEventListener(
      'focus',
      () =>
        loadGoogleFonts().then(() => {
          if (search.value !== '') {
            search.dispatchEvent(new Event('input', { bubbles: true }))
          }
        }),
      { once: true },
    )
    fontPicker.addEventListener('yw:tags-suggested', (event) => {
      previewFamilies(event.detail.values)
    })
  }

  const fontForm = rail.querySelector('[data-yw-preset-font-form]')

  /** Say what landed, where it was asked for. */
  function reportInstall(node, message, failed) {
    if (!node) return
    node.textContent = message
    node.classList.toggle('yw-preset-font-result--failed', Boolean(failed))
    node.hidden = false
  }

  /** Re-fetch the installed-faces stylesheet, so a font just downloaded can be drawn. */
  function refreshFontFaces() {
    const link = document.querySelector('[data-yw-preset-faces]')
    if (!link) return
    const url = new URL(link.href, document.baseURI)
    url.searchParams.set('installed', String(Date.now()))
    link.href = url.toString()
  }

  /** Put the families the wiki now has into every font select. */
  function rebuildWebfonts(webfonts) {
    for (const select of rail.querySelectorAll('.yw-preset-rail__font')) {
      const groups = select.querySelectorAll('optgroup')
      const group = groups[groups.length - 1]
      if (!group) continue
      const chosen = select.value
      group.replaceChildren()
      for (const [family, stack] of Object.entries(webfonts)) {
        const option = document.createElement('option')
        option.value = stack
        option.textContent = family
        option.style.fontFamily = stack
        group.appendChild(option)
      }
      select.value = chosen
      ensureOption(select, chosen)
      select.value = chosen
      showChosenFont(select)
    }
  }

  /** A webfont the wiki offers but has not downloaded yet, previewed from Google. */
  function previewChosenWebfont(stack) {
    const family = (stack.match(/^\s*'([^']+)'/) || [])[1]
    if (!family) return
    const declared = [...document.fonts].some(
      (face) => face.family.replace(/['"]/g, '') === family,
    )
    if (!declared) askGoogleFor([family])
  }

  for (const select of rail.querySelectorAll('.yw-preset-rail__font')) {
    select.addEventListener('change', () => previewChosenWebfont(select.value))
  }

  fontForm?.addEventListener('submit', (event) => {
    event.preventDefault()
    const result = fontForm.querySelector('[data-yw-preset-font-result]')
    const button = fontForm.querySelector('button[type="submit"]')
    reportInstall(result, fontForm.dataset.ywPresetFontWorking || '…', false)
    if (button) button.disabled = true

    fetch(fontForm.dataset.ywPresetFontForm, {
      method: 'POST',
      body: new FormData(fontForm),
      headers: { Accept: 'application/json' },
    })
      .then((response) => response.json().then((body) => ({ response, body })))
      .then(({ response, body }) => {
        if (!response.ok) {
          reportInstall(result, body.error || String(response.status), true)
          return
        }
        rebuildWebfonts(body.webfonts || {})
        refreshFontFaces()
        const installed = body.installed || []
        const failed = body.failed || []
        reportInstall(
          result,
          [installed.join(', '), failed.length ? `✕ ${failed.join(', ')}` : '']
            .filter(Boolean)
            .join(' — '),
          failed.length,
        )
        if (installed.length) clearFontPicker()
      })
      .catch(() => fontForm.submit())
      .finally(() => {
        if (button) button.disabled = false
      })
  })

  /** Empty the chip picker after its families have been downloaded. */
  function clearFontPicker() {
    if (!fontPicker) return
    for (const chip of fontPicker.querySelectorAll(
      '[data-yw-tag-input-remove]',
    )) {
      chip.click()
    }
  }

  const paletteBox = rail.querySelector('#yw-preset-palette')
  let paletteTarget = null

  function openPalette(name, trigger) {
    paletteTarget = name
    const [scheme] = partsOf(name)
    for (const chip of paletteBox.querySelectorAll(
      '[data-yw-preset-palette-chip]',
    )) {
      chip.style.background = asHex(
        fieldValue(`${scheme}.${chip.dataset.ywPresetPaletteChip}`),
      )
    }
    const own = partsOf(name)[1]
    for (const pick of paletteBox.querySelectorAll(
      '[data-yw-preset-palette-pick]',
    )) {
      pick.closest('li').hidden = pick.dataset.ywPresetPalettePick === own
    }

    paletteBox.hidden = false
    const box = trigger.getBoundingClientRect()
    const railBox = rail.getBoundingClientRect()
    paletteBox.style.top = `${box.bottom - railBox.top + rail.scrollTop + 4}px`
    paletteBox.querySelector('[data-yw-preset-palette-pick]')?.focus()
  }

  function closePalette() {
    paletteBox.hidden = true
    paletteTarget = null
  }

  /** Point the field at a token, or hand it back a literal colour of its own. */
  function pick(token) {
    const field = rail.querySelector(
      `[data-yw-preset-field="${paletteTarget}"]`,
    )
    if (!field) return closePalette()

    field.value = token ? `var(--${token})` : asHex(field.value)
    field.dispatchEvent(new Event('input', { bubbles: true }))
    closePalette()
  }

  if (paletteBox) {
    for (const trigger of rail.querySelectorAll(
      '[data-yw-preset-palette-open]',
    )) {
      trigger.addEventListener('click', (event) => {
        event.stopPropagation()
        const name = trigger.dataset.ywPresetPaletteOpen
        if (paletteTarget === name) return closePalette()
        openPalette(name, trigger)
      })
    }
    for (const button of paletteBox.querySelectorAll(
      '[data-yw-preset-palette-pick]',
    )) {
      button.addEventListener('click', () =>
        pick(button.dataset.ywPresetPalettePick),
      )
    }
    paletteBox
      .querySelector('[data-yw-preset-palette-close]')
      ?.addEventListener('click', closePalette)
    palettes.push({ box: paletteBox, close: closePalette })
  }

  function open(button, { isNew }) {
    const values = JSON.parse(button.dataset.presetValues || '{}')

    undoPreview()

    for (const field of fields) {
      const name = field.dataset.ywPresetField
      ensureOption(field, valueOf(values, name))
      field.value = valueOf(values, name)
      showChosenFont(field)
      const picker = pickerFor(name)
      if (picker) picker.value = asHex(field.value)
      const slider = sliderFor(name)
      if (slider) {
        slider.value = asSliderValue(slider, field.value)
        field.value = measureOf(slider)
        showMeasure(name, slider)
      }
      preview(name, field.value)
    }
    showInks()
    showPickers()
    showContrast()

    idField.value = isNew ? '' : button.dataset.presetId || ''
    nameField.value = isNew ? '' : button.dataset.presetName || ''

    title.textContent = isNew ? title.dataset.newLabel : title.dataset.editLabel

    showScheme()
    showScreen('edit')
    rail.hidden = false
    nameField.focus()
  }

  function screenOf(name) {
    return rail.querySelector(`[data-yw-preset-screen="${name}"]`)
  }

  /** Show the half of the preset that matches the page's Colour scheme, and say which it is. */
  function showScheme() {
    const scheme = currentScheme()
    for (const block of schemeBlocks) {
      block.hidden = block.dataset.scheme !== scheme
    }
    if (schemeNote) {
      schemeNote.textContent =
        scheme === 'dark' ? schemeNote.dataset.dark : schemeNote.dataset.light
    }
  }

  /** The page's scheme changed under us -- show and repaint from the half now in force. */
  function schemeChanged() {
    showScheme()
    if (rail.hidden || screenOf('edit').hidden) return
    undoPreview()
    for (const field of fields)
      preview(field.dataset.ywPresetField, field.value)
    showInks()
    showPickers()
    showContrast()
  }

  /** Which face of the drawer is showing. */
  function showScreen(name) {
    for (const screen of screens) {
      screen.hidden = screen.dataset.ywPresetScreen !== name
    }
  }

  /** Leave the editor for the list. */
  function back() {
    undoPreview()
    closePalette()
    showScreen('list')
  }

  /** Shut the drawer entirely -- the gallery is what you want to look at. */
  function close() {
    back()
    rail.hidden = true
  }

  document.querySelectorAll('[data-yw-preset-edit]').forEach((button) => {
    button.addEventListener('click', () => open(button, { isNew: false }))
  })
  document.querySelectorAll('[data-yw-preset-new]').forEach((button) => {
    button.addEventListener('click', () => open(button, { isNew: true }))
  })
  rail.querySelector('[data-yw-preset-back]')?.addEventListener('click', back)
  rail
    .querySelector('[data-yw-preset-close-rail]')
    ?.addEventListener('click', close)
  document.querySelectorAll('[data-yw-preset-open]').forEach((button) => {
    button.addEventListener('click', () => {
      rail.hidden = false
    })
  })

  for (const field of fields) {
    const name = field.dataset.ywPresetField
    const picker = pickerFor(name)
    const slider = sliderFor(name)
    field.addEventListener('input', () => {
      showChosenFont(field)
      preview(name, field.value)
      showInks()
      showPickers()
      showContrast()
    })
    picker?.addEventListener('input', () => {
      field.value = picker.value
      preview(name, field.value)
      showInks()
      showPickers()
      showContrast()
    })
    slider?.addEventListener('input', () => {
      field.value = measureOf(slider)
      showMeasure(name, slider)
      preview(name, field.value)
    })
  }

  new MutationObserver(schemeChanged).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['data-theme'],
  })
  window
    .matchMedia('(prefers-color-scheme: dark)')
    .addEventListener('change', schemeChanged)

  showScheme()

  form?.addEventListener('submit', () => beforePreview.clear())

  document
    .querySelectorAll('[data-yw-preset-delete-form]')
    .forEach((deleteForm) => {
      deleteForm.addEventListener('submit', (event) => {
        if (!window.confirm(deleteForm.dataset.confirm)) event.preventDefault()
      })
    })
})
