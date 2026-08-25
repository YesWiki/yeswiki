/**
 * Every palette popover on the page, so one pair of document listeners serves them all.
 *
 * A popover that only closes by its own button is one people leave open over the field they were
 * trying to look at, so a click elsewhere and Escape both shut it. These sit on `document` and are
 * registered once for the page: htmx swaps the rail in and out, and a listener added per swap
 * would pile up, each one holding a box that is no longer in the document. `isConnected` retires
 * those instead.
 */
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

  /** The one link this screen adds; there is only ever one preset being tried on. */
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

  /** Paint one token onto the document, so the gallery below repaints with it. */
  function preview(name, value) {
    const [scheme, token] = partsOf(name)
    if (scheme !== currentScheme() && !isSchemeIndependent(token)) return
    if (!beforePreview.has(token)) {
      beforePreview.set(
        token,
        document.documentElement.style.getPropertyValue(`--${token}`),
      )
    }
    document.documentElement.style.setProperty(`--${token}`, value)
  }

  function undoPreview() {
    undoInks()
    beforePreview.forEach((value, token) => {
      if (value) document.documentElement.style.setProperty(`--${token}`, value)
      else document.documentElement.style.removeProperty(`--${token}`)
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

  /** A colour as [r, g, b], whatever notation it was written in. */
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

  /** The grade a ratio earns, by WCAG 2.1: AAA 7 -- body text, the. */
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

  /** what showInks() has painted, so closing the rail takes it off like every other. */
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

  /** Draw each suggested family in its own face. */
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
    // `focus` and not `input`: the list has to be there before the first keystroke is
    // filtered against it, or the first thing typed suggests nothing.
    //
    // ...which focus alone does NOT guarantee, because the fetch takes as long as it takes:
    // type "Lob" quickly enough and all three keystrokes are filtered against an empty
    // catalogue, leaving a box with text in it and no suggestions under it -- indistinguishable
    // from a family Google does not have. So the arrival of the list re-runs the filter
    // against whatever has been typed by then.
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

  // ---- downloading a webfont, without losing the preset being edited -------------------
  //
  // This was a form POST: it installed the font, redirected back to /admin/preset, and the
  // rail came back on the LIST screen with every unsaved edit gone. Adding a font is a
  // thought you have in the middle of designing a preset -- "the one I want is not in this
  // list" -- so the one moment you reach for it is the moment losing the screen costs most.
  //
  // The endpoint does the same work and answers with what happened, so the screen stays
  // exactly as it was and grows two options in the selects. Falls back to the page POST when
  // the fetch cannot be made at all, which is still better than nothing having happened.
  const fontForm = rail.querySelector('[data-yw-preset-font-form]')

  /** Say what landed, where it was asked for. */
  function reportInstall(node, message, failed) {
    if (!node) return
    node.textContent = message
    node.classList.toggle('yw-preset-font-result--failed', Boolean(failed))
    node.hidden = false
  }

  /**
   * Re-fetch the installed-faces stylesheet, so a font just downloaded can be drawn.
   *
   * A new href, not `link.href = link.href`: an identical URL is a cache hit and the browser
   * does not go back to the server, which is precisely the case here -- the file did not
   * change, its *contents* did.
   */
  function refreshFontFaces() {
    const link = document.querySelector('[data-yw-preset-faces]')
    if (!link) return
    const url = new URL(link.href, document.baseURI)
    url.searchParams.set('installed', String(Date.now()))
    link.href = url.toString()
  }

  /**
   * Put the families the wiki now has into every font select.
   *
   * The webfont group is rebuilt from the server's own answer rather than by appending what
   * was typed: what a family is *called* and what stack it becomes are the service's to say,
   * and a guess here would put a slightly different string in the select from the one a
   * reload produces. Each select keeps its own value, because this is not a change to the
   * preset -- it is a change to what may be chosen.
   */
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
      // the value survives the rebuild, or is put back as an option of its own if the
      // preset names something no longer offered -- same contract as on first render
      select.value = chosen
      ensureOption(select, chosen)
      select.value = chosen
      showChosenFont(select)
    }
  }

  /**
   * A webfont the wiki offers but has not downloaded yet, previewed from Google.
   *
   * The curated list names sixteen families; a wiki has downloaded however many of them it
   * has actually used. Choosing one of the others changed `--yw-font-body` and nothing else
   * moved -- correct, since the file arrives when the preset is SAVED, and indistinguishable
   * from the choice not registering.
   *
   * So the admin's browser asks Google for it, exactly as the picker does when drawing its
   * suggestions: a webmaster deliberately looking at typefaces, on an admin screen. Nothing
   * on a public page changes, and no reader is ever handed to Google -- the saved preset is
   * served from this wiki.
   *
   * Installed families are already declared by the faces stylesheet, so `check()` answers for
   * them and nothing is asked. On an instance that cannot reach Google the request fails
   * soundlessly and the preview is what it was before: the fallback.
   */
  function previewChosenWebfont(stack) {
    const family = (stack.match(/^\s*'([^']+)'/) || [])[1]
    if (!family) return
    // The REGISTRY, not `document.fonts.check()`. With no `@font-face` for a family, `check`
    // resolves it to a system font and answers *true* -- which is precisely the case this
    // exists for, so guarding on it means never asking. A face is in the registry only
    // because a rule declared it, which is the actual question.
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
        // the chips go, because they have been acted on: leaving them would make a second
        // press re-download what is already here
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

  // ---- the palette -------------------------------------------------------------------
  //
  // Pointing one colour AT another instead of giving it a value: the field takes
  // `var(--yw-primary)` and the two stay in step afterwards. One popover for all forty-four
  // colour fields, because its fourteen chips are painted from the live values every time it
  // opens -- a palette per field would be forty-four copies to keep in sync as you drag.
  //
  // What makes this work at all is that a custom property may refer to another: measured, a
  // reference resolves, the derived tier resolves *through* it, and it follows a later
  // redefinition, so `var(--yw-primary)` written in the dark block picks the dark primary by
  // itself. What it does NOT do is complain about a loop -- every colour in one silently
  // computes to black -- which is why PresetService refuses to save one.

  const paletteBox = rail.querySelector('#yw-preset-palette')
  /** Which field the palette was opened for, as `light.yw-heading-1`. */
  let paletteTarget = null

  function openPalette(name, trigger) {
    paletteTarget = name
    // the chips show what each colour IS right now, in the scheme being edited -- the same
    // resolution the picker swatches use, so the popover and the fields agree
    const [scheme] = partsOf(name)
    for (const chip of paletteBox.querySelectorAll(
      '[data-yw-preset-palette-chip]',
    )) {
      chip.style.background = asHex(
        fieldValue(`${scheme}.${chip.dataset.ywPresetPaletteChip}`),
      )
    }
    // a colour cannot point at itself, so it is not offered to itself
    const own = partsOf(name)[1]
    for (const pick of paletteBox.querySelectorAll(
      '[data-yw-preset-palette-pick]',
    )) {
      pick.closest('li').hidden = pick.dataset.ywPresetPalettePick === own
    }

    paletteBox.hidden = false
    // under the button that opened it, inside the rail's own scroll
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

    // "a colour of its own" resolves the reference to what it currently looks like, so
    // un-pointing keeps the colour on screen and only drops the relationship
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
    // anywhere else, and Escape: a popover that only closes by its own button is one people
    // leave open over the field they were trying to look at
    palettes.push({ box: paletteBox, close: closePalette })
  }

  function open(button, { isNew }) {
    const values = JSON.parse(button.dataset.presetValues || '{}')

    undoPreview()

    for (const field of fields) {
      const name = field.dataset.ywPresetField
      // before the assignment: a select drops a value it has no option for, silently
      ensureOption(field, valueOf(values, name))
      field.value = valueOf(values, name)
      showChosenFont(field)
      const picker = pickerFor(name)
      if (picker) picker.value = asHex(field.value)
      const slider = sliderFor(name)
      if (slider) {
        slider.value = asSliderValue(slider, field.value)
        // the field is hidden and the slider may have snapped: what posts has to be what is
        // on screen, or saving would write back the unreachable value the rail opened on
        field.value = measureOf(slider)
        showMeasure(name, slider)
      }
      preview(name, field.value)
    }
    // after the loop, not inside it: a field pointed at another colour can only be resolved
    // once that other colour has been painted onto the document too
    showInks()
    showPickers()
    showContrast()

    // Which preset the save rewrites. Empty for a new one -- that is the only case here
    // that creates a file; editing always replaces the one it was opened on, even when the
    // name in the box changes, in which case the file is renamed.
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

  /**
   * Show the half of the preset that matches the page's Colour scheme, and say which it is.
   *
   * Both halves stay in the DOM and both post -- a Preset is two complete sets of colours and
   * a save writes both. Only one is on screen, because only one can be PREVIEWED: the document
   * is in one scheme at a time, so a field for the other is a control with no visible effect,
   * which is exactly what the second column used to be.
   */
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
    // the fills flip with the scheme, so the ink each one earns is a different answer now
    showInks()
    showPickers()
    showContrast()
  }

  /** Which face of the drawer is showing. The other is `hidden`, and that is the whole state. */
  function showScreen(name) {
    for (const screen of screens) {
      screen.hidden = screen.dataset.ywPresetScreen !== name
    }
  }

  /**
   * Leave the editor for the list.
   *
   * The live preview goes with it, because the editor is what was producing it: staying on
   * the list wearing the half-finished colours of a preset you backed out of would be a wiki
   * showing you something that is not saved anywhere and cannot be got back to.
   */
  function back() {
    undoPreview()
    closePalette()
    showScreen('list')
  }

  /**
   * Shut the drawer entirely -- the gallery is what you want to look at.
   *
   * Returns to the list on the way out, so reopening lands where the drawer is *for*: the
   * editor is a place you go from the list, never a place you find the drawer already in.
   */
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
      // every swatch and every badge, not just this field's: a colour can be pointed at
      // another one and is scored against another one, so changing a surface repaints and
      // re-grades everything that refers to it
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
    // A measure is the slider and nothing else now: it produces the only values the token
    // can hold, which is what let the text box beside it go.
    slider?.addEventListener('input', () => {
      field.value = measureOf(slider)
      showMeasure(name, slider)
      preview(name, field.value)
    })
  }

  // A scheme that changes under an open drawer means the other half of the fields is now the
  // half to show and to paint from. It changes two ways and BOTH have to be watched:
  //
  //  - the wiki's own toggle, which sets `data-theme` on <html> -- an attribute, so nothing
  //    fires an event; a MutationObserver is the only way to hear it;
  //  - the OS flipping at dusk, for a viewer who has not chosen -- `prefers-color-scheme`.
  //
  // Only the second was watched, and the first is the one people actually use, so the toggle
  // appeared to do nothing on this screen whenever the editor was open: `preview()` writes the
  // tokens INLINE on <html>, which beats the scheme blocks in the stylesheet, so every
  // previewed colour stayed at its light value and the page stayed white.
  new MutationObserver(schemeChanged).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['data-theme'],
  })
  window
    .matchMedia('(prefers-color-scheme: dark)')
    .addEventListener('change', schemeChanged)

  // and the right half is on screen before anything is opened
  showScheme()

  // Saving is a normal submit, and the page it lands on is the one wearing the preset --
  // so the inline preview must not be what puts it back on screen afterwards.
  form?.addEventListener('submit', () => beforePreview.clear())

  // Deleting a preset removes a file; every other button here is reversible.
  document
    .querySelectorAll('[data-yw-preset-delete-form]')
    .forEach((deleteForm) => {
      deleteForm.addEventListener('submit', (event) => {
        if (!window.confirm(deleteForm.dataset.confirm)) event.preventDefault()
      })
    })
})
