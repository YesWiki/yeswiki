// javascripts/admin-preset.js -- the Personnalisation screen (ticket 30).
//
// Two things happen here, and keeping them apart is the point of the screen:
//
//  - **Trying a preset on.** Clicking a card restyles THIS page and nothing else. It swaps
//    a stylesheet link in the head, so what you see is the whole preset -- its webfonts and
//    any rule it carries beyond the token set -- and not an approximation of it.
//    Nothing is written; reloading the page ends it.
//  - **Editing one**, in the drawer: the same docked <aside> the actions builder and the
//    file picker use. A colour is a picker beside a text field; a MEASURE is a slider and
//    nothing else (ADR-0021), so the three functions below that deal with one --
//    asSliderValue, measureOf, readoutTextFor -- are the whole of how a spacing step or a
//    corner scale is chosen.
//
// The drawer holds both, on two screens: the list of presets, and the editor a card's pencil
// opens. So there are two pieces of state here and they are deliberately different things --
// `rail.hidden` is whether the drawer is on screen at all, and showScreen() is which of its
// two faces you are looking at. Shutting the drawer from the editor and reopening it must
// land you back on the list, which is why close() does both.
//
// Making a preset the wiki's is neither: it is a plain form posting to this route, so it
// keeps working on a page whose JavaScript never arrived. So do deleting and duplicating.

const rail = document.getElementById('yw-preset-rail')

if (rail) {
  const form = rail.querySelector('form')
  const screens = [...rail.querySelectorAll('[data-yw-preset-screen]')]
  // anything carrying `data-scheme`, not just the field blocks: the contrast badge sits on
  // the label line now and is per scheme too, so it has to swap with them
  const schemeBlocks = [...rail.querySelectorAll('[data-scheme]')]
  const schemeNote = rail.querySelector('[data-yw-preset-scheme-note]')
  const title = rail.querySelector('[data-yw-preset-rail-title]')
  const idField = rail.querySelector('[data-yw-preset-id]')
  const nameField = rail.querySelector('#yw-preset-name')
  const fields = [...rail.querySelectorAll('[data-yw-preset-field]')]

  // The wiki's own preset, as linked by CoreAssets. Switched off while another one is being
  // tried on -- an added link would otherwise have to out-specify it rather than replace it.
  const wikiPreset = document.getElementById('wikipreset')

  /** The one link this screen adds; there is only ever one preset being tried on. */
  let tryLink = null

  /**
   * Wear a preset on this page alone.
   *
   * `href` empty means the theme's own colours: the wiki's preset goes off and nothing
   * replaces it, which is exactly what "no preset" is.
   */
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

  // What the document looked like before the rail touched it, so closing the rail puts it
  // back. Only the tokens the rail writes are recorded: everything else on :root belongs
  // to the stylesheets and must be left alone.
  const beforePreview = new Map()

  /**
   * Which Colour scheme this page is being read in.
   *
   * A field belongs to one scheme, and only one of the two can be on screen: painting the
   * dark set onto a light document would preview a page nobody will ever see. So the rail
   * previews the scheme the viewer is in and leaves the other pair of values to the file.
   */
  function currentScheme() {
    if (document.documentElement.dataset.theme) {
      return document.documentElement.dataset.theme
    }
    return window.matchMedia('(prefers-color-scheme: dark)').matches
      ? 'dark'
      : 'light'
  }

  /** `light.yw-primary` -> ['light', 'yw-primary'] */
  function partsOf(name) {
    const separator = name.indexOf('.')
    return [name.slice(0, separator), name.slice(separator + 1)]
  }

  /**
   * Is this token one value for both schemes -- a measure, a font, `--yw-text-on-dark`?
   *
   * Read off the rail rather than declared twice: a colour is the only kind rendered with a
   * field per scheme, so a `light.x` with no `dark.x` beside it IS the scheme-independent
   * set. That keeps this in step with PresetService::TOKENS by construction.
   */
  function isSchemeIndependent(token) {
    return !rail.querySelector(`[data-yw-preset-field="dark.${token}"]`)
  }

  /**
   * Paint one token onto the document, so the gallery below repaints with it.
   *
   * A colour is painted only when its scheme is the one on screen -- painting the dark set
   * onto a light document would preview a page nobody will ever see. Everything else is
   * painted always, because there is only one of it: the spacing steps, the corner scale and
   * the type size live under `light.` for want of anywhere else to put them, and a webmaster
   * reading the wiki in dark mode was watching a slider that appeared to do nothing.
   *
   * Painting the AUTHORED token is enough for the derived ones too: `--yw-radius-md` is
   * `calc(0.25rem * var(--yw-radius-scale))` and resolves against whatever `:root` says now,
   * so one inline property repaints every corner in the gallery.
   */
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

  /**
   * Where the slider sits for a value it may not be able to express.
   *
   * A measure has no text box beside it any more (ADR-0021), so this is the only place a
   * hand-written `clamp()` or `1.05rem` can land -- it is snapped to the nearest position
   * the slider has, and saving then writes that. Deliberate: the whole point of dropping
   * the text box was that a measure is a multiple of the base size and nothing else, and a
   * value the slider cannot reach is a value this screen cannot show you the effect of.
   *
   * A value it cannot read at all (a `var()`, an empty field on a brand new preset) lands on
   * the slider's own default rather than its floor: `--yw-space-lg: 0` is a legal setting and
   * a silently collapsed layout is a bad way to find that out.
   */
  function asSliderValue(slider, value) {
    const min = Number(slider.min)
    const max = Number(slider.max)
    const step = Number(slider.step) || 1
    const size = parseFloat(String(value ?? '').trim())
    if (!Number.isFinite(size))
      return slider.defaultValue || String((min + max) / 2)
    const snapped = Math.round((size - min) / step) * step + min
    // toFixed, then trimmed: 0.05 steps accumulate float error, and a slider reporting
    // `0.7500000000000001rem` would write that into the file
    return String(Number(Math.min(max, Math.max(min, snapped)).toFixed(4)))
  }

  /** The value a slider posts: its number with the token's own unit put back on. */
  function measureOf(slider) {
    return `${slider.value}${slider.dataset.unit || ''}`
  }

  /**
   * What the readout says: the number as the thing it measures.
   *
   * `rem` IS the wiki's base type size here (core computes `html`'s font-size from
   * `--yw-font-size-base`), so a spacing step reads as a multiple rather than as a length --
   * which is what it is, and what makes it survive somebody changing the type size.
   */
  function readoutTextFor(slider) {
    const unit = slider.dataset.unit || ''
    if (unit === 'px') return `${slider.value}px`
    return `${slider.value}\u00d7`
  }

  /**
   * Let a font select hold a value that is not one of the offered stacks.
   *
   * A preset naming a webfont, or a stack somebody wrote by hand, is a value this select has
   * no option for -- and a select handed one of those keeps whatever was selected before,
   * which would rewrite somebody's font the moment the rail opened on it. So the value is
   * added as an option of its own, showing itself, drawn in itself.
   *
   * One at a time: the option is replaced on every open, or editing three presets in a row
   * would leave the other two's fonts in the list.
   */
  function ensureOption(field, value) {
    if (field.tagName !== 'SELECT') return
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
    if (field.tagName === 'SELECT') field.style.fontFamily = field.value
  }

  /** Put the number a slider is on into words beside it. */
  function showMeasure(name, slider) {
    const readout = readoutFor(name)
    if (readout) readout.textContent = readoutTextFor(slider)
  }

  /**
   * A `#rrggbb` the native picker will accept, for anything a colour can be written as.
   *
   * The picker reads what it does not understand as black and then reports black back as the
   * value, which would turn a `var(--yw-primary)` or an `rgb()` into black the moment the
   * rail opened on it -- so the picker is only ever *shown* a hex and the text field keeps
   * the truth.
   *
   * It used to show black for all of those. Now the probe resolves them, so a field pointed
   * at another colour shows THAT colour in its swatch -- which is the whole of how you can
   * see what `var(--yw-primary)` means without reading it.
   */
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

  // ---- contrast --------------------------------------------------------------------
  //
  // What the badges beside each colour say. The pairing is the SERVER's -- a token declares
  // what it has to be legible against (PresetService::TOKENS `contrast`) -- and the scoring
  // is here, because it has to follow the fields as they are dragged.
  //
  // Per scheme, deliberately: ink that clears AA on a white page can fail on the near-black
  // one, and that is the single most common way a hand-authored dark set goes wrong.

  const badges = [...rail.querySelectorAll('[data-yw-preset-contrast]')]

  /**
   * A colour as [r, g, b], whatever notation it was written in.
   *
   * Through a probe element rather than by parsing: a field can hold `rgb(0 0 0 / 50%)`,
   * `hsl(...)`, `color-mix(...)` or a bare keyword, and the browser already knows how to
   * read every one of them. It must be IN the document -- getComputedStyle on a detached
   * element returns nothing.
   */
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

  /**
   * The grade a ratio earns, by WCAG 2.1:
   *   AAA       7   -- body text, the standard to aim for
   *   AA        4.5 -- body text, the one that is normally required
   *   AA-large  3   -- headings and 24px+ only; body text at this ratio fails
   */
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

  /**
   * Re-sync every colour swatch from its field.
   *
   * All of them, not just the one that changed: a field holding `var(--yw-primary)` shows the
   * brand colour in its swatch, so moving the brand has to repaint every swatch pointed at
   * it. Only the edited field used to be updated, which left a heading pointed at the brand
   * rendering the new colour on the page while its own swatch still showed the old one.
   *
   * Always after `preview()`: the probe resolves `var()` against the document, so the inline
   * values have to be on it before this can read them.
   */
  function showPickers() {
    for (const field of fields) {
      const picker = pickerFor(field.dataset.ywPresetField)
      if (picker) picker.value = asHex(field.value)
    }
  }

  // Which fills get an automatically-chosen ink, and where the choice is written. The same
  // map as PresetService::INK_FOR -- rendered onto the rail rather than repeated here, so the
  // two cannot drift.
  const inkFor = JSON.parse(rail.dataset.ywPresetInkFor || '{}')

  /**
   * Paint the ink each fill has earned, and keep it painted while the fields move.
   *
   * CSS cannot do this one: choosing between two AUTHORED colours needs the luminance of a
   * third, and no CSS function hands you that as a number (`oklch(from …)` and
   * `contrast-color()` both work here, and both only ever answer black or white). So the
   * choice is made in PHP when the preset is saved -- and here, live, or the gallery would
   * show yesterday's ink on today's button all the way through an edit.
   */
  /** what showInks() has painted, so closing the rail takes it off like every other preview */
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

  /**
   * What a colour is scored against.
   *
   * `auto-ink` means "whatever ink this fill will actually get" -- a fill has no fixed
   * partner, so naming one in the token table would score the pair that does not happen.
   */
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
    document.addEventListener('click', (event) => {
      if (!paletteBox.hidden && !paletteBox.contains(event.target))
        closePalette()
    })
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !paletteBox.hidden) closePalette()
    })
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
}
