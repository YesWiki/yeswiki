import FilePickerPanel from '../file-picker-panel.js'

/** One panel per page, shared by every field on the form (they open it in turn). */
let picker = null

const openPicker = (button) => {
  const target = document.getElementById(button.dataset.ywFilePickerField)
  if (!target) return

  picker ??= new FilePickerPanel()
  picker.open({
    only: button.dataset.ywFilePickerOnly || '',
    onPick: ({ url, entry }) => {
      target.value = url
      const chosen = document.querySelector(
        `[data-yw-file-picker-chosen="${button.dataset.ywFilePickerField}"]`,
      )
      if (chosen) {
        chosen.textContent = entry.original_filename
        chosen.hidden = false
      }
      target.dispatchEvent(new Event('change', { bubbles: true }))
    },
  })
}

document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-yw-file-picker-field]')
  if (!button) return
  event.preventDefault()
  openPicker(button)
})
