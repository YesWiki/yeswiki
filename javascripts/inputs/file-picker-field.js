// javascripts/inputs/file-picker-field.js -- the bazar file and image fields attach a
// file through the same rail the editors use (javascripts/file-picker-panel.js): pick one
// of the files this wiki already holds, or upload a new one there.
//
// What the field stores is the file's own address (`api/files/<tag>/download`), which is
// the URL branch both fields have always had -- so nothing new had to be taught to
// ImageField/FileField about where a value comes from, and the URL box beside the button
// is the same box, still hand-typeable for a file that lives somewhere else entirely.
//
// The panel itself is on the page already: every entry form includes
// @core/aceditor-rails.twig, which carries all three rails whether or not anything on the
// form is an editor.
import FilePickerPanel from '../file-picker-panel.js'

/** One panel per page, shared by every field on the form (they open it in turn). */
let picker = null

const openPicker = (button) => {
  const target = document.getElementById(button.dataset.ywFilePickerField)
  if (!target) return

  picker ??= new FilePickerPanel()
  picker.open({
    // an image field asks for `only="image"`: the rail then shows pictures and nothing
    // else, rather than offering a PDF the field could not display
    only: button.dataset.ywFilePickerOnly || '',
    onPick: ({ url, entry }) => {
      target.value = url
      // the field posts `<name>_url`, and a browser only submits what it can see:
      // an input inside a hidden tab pane is submitted, but the person filling the form
      // has to be shown what they just chose
      const chosen = document.querySelector(`[data-yw-file-picker-chosen="${button.dataset.ywFilePickerField}"]`)
      if (chosen) {
        chosen.textContent = entry.original_filename
        chosen.hidden = false
      }
      target.dispatchEvent(new Event('change', { bubbles: true }))
    }
  })
}

document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-yw-file-picker-field]')
  if (!button) return
  event.preventDefault()
  openPicker(button)
})
