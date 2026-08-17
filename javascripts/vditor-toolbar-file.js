import FilePickerPanel from './file-picker-panel.js'
import { legacyIconToSprite } from './yw-icon-map.js'

let filePicker = null

/** The rail lives outside the form (it contains one of its own), so it is included by the page template rather than by the field. */
export const hasFilePicker = () =>
  document.getElementById('YesWikiFilePickerPanel') !== null

/** Whether a file is being chosen right now. */
export const filePickerIsOpen = () => Boolean(filePicker?.isOpen)

/** The file button, for either of the two Vditors. */
export const filePickerMenuItem = ({ format = 'markdown', onComplete }) => ({
  name: 'yw-file',
  tip: _t('UPLOAD_A_FILE'),
  tipPosition: 'n',
  icon: legacyIconToSprite('upload'),
  click() {
    filePicker ??= new FilePickerPanel()
    filePicker.open({ format, onComplete })
  },
})
