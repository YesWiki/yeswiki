import FilePickerPanel from './file-picker-panel.js'
import { legacyIconToSprite } from './yw-icon-map.js'

let filePicker = null

/** The rail lives outside the form (it contains one of its own), so it is included by the page template rather than by the field. */
export const hasFilePicker = () =>
  document.getElementById('YesWikiFilePickerPanel') !== null

/** Whether a file is being chosen right now. */
export const filePickerIsOpen = () => Boolean(filePicker?.isOpen)

/** The file button, for either of the two Vditors; target() gives the page and upload token when the editor is a field's. */
export const filePickerMenuItem = ({
  format = 'markdown',
  labelled = false,
  onComplete,
  target = () => ({}),
}) => ({
  name: 'yw-file',
  tip: _t('UPLOAD_A_FILE'),
  tipPosition: 'n',
  icon: labelled
    ? `${legacyIconToSprite('upload')}<span>${_t('UPLOAD_A_FILE_SHORT')}</span>`
    : legacyIconToSprite('upload'),
  click() {
    filePicker ??= new FilePickerPanel()
    filePicker.open({ format, onComplete, ...target() })
  },
})
