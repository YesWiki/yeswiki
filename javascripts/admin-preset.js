// javascripts/admin-preset.js -- the Personnalisation screen (ticket 30).
//
// Two things happen here, and keeping them apart is the point of the screen:
//
//  - **Trying a preset on.** Clicking a card restyles THIS page and nothing else. It swaps
//    a stylesheet link in the head, so what you see is the whole preset -- its webfonts and
//    any rule it carries beyond the nine variables -- and not an approximation of it.
//    Nothing is written; reloading the page ends it.
//  - **Editing one**, in the rail: the same docked <aside> the actions builder and the file
//    picker use, where `hidden` is the whole of its state.
//
// Making a preset the wiki's is neither: it is a plain form posting to this route, so it
// keeps working on a page whose JavaScript never arrived. So do deleting and duplicating.

const rail = document.getElementById('yw-preset-rail')

if (rail) {
  const form = rail.querySelector('form')
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
  // back. Only the variables the rail writes are recorded: everything else on :root belongs
  // to the stylesheets and must be left alone.
  const beforePreview = new Map()

  /** Paint one variable onto the document, so the gallery below repaints with it. */
  function preview(name, value) {
    if (!beforePreview.has(name)) {
      beforePreview.set(
        name,
        document.documentElement.style.getPropertyValue(`--${name}`),
      )
    }
    document.documentElement.style.setProperty(`--${name}`, value)
  }

  function undoPreview() {
    beforePreview.forEach((value, name) => {
      if (value) document.documentElement.style.setProperty(`--${name}`, value)
      else document.documentElement.style.removeProperty(`--${name}`)
    })
    beforePreview.clear()
  }

  /** The picker beside a text field, where the field is a colour. */
  function pickerFor(name) {
    return rail.querySelector(`[data-yw-preset-picker="${name}"]`)
  }

  /** The slider beside a text field, where the field is a size. */
  function sliderFor(name) {
    return rail.querySelector(`[data-yw-preset-slider="${name}"]`)
  }

  /**
   * Where the slider sits for a value it may not be able to express.
   *
   * The same contract as asHex: the control is only ever *shown* something it can hold, and
   * the text field beside it keeps the truth. A preset written by hand can say `1.05rem`,
   * which reads here as 1 and lands on the slider's floor -- until the slider is actually
   * moved, at which point the person has chosen pixels and the field says so.
   */
  function asSliderValue(slider, value) {
    const min = Number(slider.min)
    const max = Number(slider.max)
    const size = parseFloat(String(value ?? '').trim())
    if (!Number.isFinite(size)) return String(Math.round((min + max) / 2))
    return String(Math.min(max, Math.max(min, Math.round(size))))
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

  /**
   * A `#rrggbb` the native picker will accept. It reads anything else as black and then
   * reports black back as the value, which would turn a `var(--x)` or an `rgb()` into
   * black the moment the rail opened on it -- so the picker is only ever *shown* a hex,
   * and the text field keeps the truth.
   */
  function asHex(value) {
    const trimmed = String(value ?? '').trim()
    if (/^#[0-9a-f]{6}$/i.test(trimmed)) return trimmed
    if (/^#[0-9a-f]{3}$/i.test(trimmed)) {
      return `#${trimmed[1]}${trimmed[1]}${trimmed[2]}${trimmed[2]}${trimmed[3]}${trimmed[3]}`
    }
    return '#000000'
  }

  function open(button, { isNew }) {
    const values = JSON.parse(button.dataset.presetValues || '{}')

    undoPreview()

    for (const field of fields) {
      const name = field.dataset.ywPresetField
      // before the assignment: a select drops a value it has no option for, silently
      ensureOption(field, values[name] ?? '')
      field.value = values[name] ?? ''
      showChosenFont(field)
      const picker = pickerFor(name)
      if (picker) picker.value = asHex(field.value)
      const slider = sliderFor(name)
      if (slider) slider.value = asSliderValue(slider, field.value)
      preview(name, field.value)
    }

    // Which preset the save rewrites. Empty for a new one -- that is the only case here
    // that creates a file; editing always replaces the one it was opened on, even when the
    // name in the box changes, in which case the file is renamed.
    idField.value = isNew ? '' : button.dataset.presetId || ''
    nameField.value = isNew ? '' : button.dataset.presetName || ''

    title.textContent = isNew ? title.dataset.newLabel : title.dataset.editLabel

    rail.hidden = false
    nameField.focus()
  }

  function close() {
    rail.hidden = true
    // the preview goes with it: the rail is shut, so what is on screen has to be what the
    // wiki actually looks like again
    undoPreview()
  }

  document.querySelectorAll('[data-yw-preset-edit]').forEach((button) => {
    button.addEventListener('click', () => open(button, { isNew: false }))
  })
  document.querySelectorAll('[data-yw-preset-new]').forEach((button) => {
    button.addEventListener('click', () => open(button, { isNew: true }))
  })
  rail.querySelector('[data-yw-preset-close]')?.addEventListener('click', close)

  for (const field of fields) {
    const name = field.dataset.ywPresetField
    const picker = pickerFor(name)
    const slider = sliderFor(name)
    field.addEventListener('input', () => {
      if (picker) picker.value = asHex(field.value)
      if (slider) slider.value = asSliderValue(slider, field.value)
      showChosenFont(field)
      preview(name, field.value)
    })
    picker?.addEventListener('input', () => {
      field.value = picker.value
      preview(name, field.value)
    })
    // the slider only ever produces pixels, which is what makes it a slider: a length it
    // cannot express is one it cannot land on either
    slider?.addEventListener('input', () => {
      field.value = `${slider.value}px`
      preview(name, field.value)
    })
  }

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
