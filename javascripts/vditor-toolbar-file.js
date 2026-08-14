import FilePickerPanel from './file-picker-panel.js'
import { legacyIconToSprite } from './yw-icon-map.js'

// One picker for the document, built on first use: its constructor binds to the single
// #YesWikiFilePickerPanel the page carries, which several editors on one form share.
let filePicker = null

/**
 * The rail lives outside the form (it contains one of its own), so it is included by the
 * page template rather than by the field. A Vditor rendered somewhere that never included
 * it gets its other buttons and no file button, rather than one that throws on click.
 */
export const hasFilePicker = () =>
  document.getElementById('YesWikiFilePickerPanel') !== null

/**
 * Whether a file is being chosen right now.
 *
 * For an editor deciding whether a click in the document means "I have left the rail":
 * this one is placing something the document does not have yet, and where it goes is
 * decided by clicking in the document. The source editor makes the same exception, from
 * the other side (`syncRailsWithCursor`).
 */
export const filePickerIsOpen = () => Boolean(filePicker?.isOpen)

/**
 * The file button, for either of the two Vditors. They differ in what the picker writes --
 * Markdown for the HTML fields, an `{{attach}}` component for the wiki-syntax ones -- and
 * in where it goes, so both are the caller's to say.
 *
 * `onComplete` is called much later than the toolbar is declared, which is why nothing
 * here holds the editor: the item is built before `new Vditor()` returns. Note also that
 * Vditor hands `click` its own *internal* state object, which has none of the public API,
 * so the argument it passes cannot be used for anything.
 */
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
