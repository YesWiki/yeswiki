import { openIconPicker, iconHtml } from '../icon-picker.js'

document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-yw-icon-field-open]')
  if (!button) return
  event.preventDefault()
  const field = button.closest('[data-yw-icon-field]')
  const source = field.querySelector('[data-yw-icon-field-source]')
  const value = field.querySelector('[data-yw-icon-field-value]')
  openIconPicker({
    anchor: button,
    value: { source: source.value, value: value.value },
    onPick: (icon) => {
      source.value = icon.source
      value.value = icon.value
      field.querySelector('[data-yw-icon-field-preview]').innerHTML =
        iconHtml(icon)
      value.dispatchEvent(new Event('change', { bubbles: true }))
    },
  })
})
