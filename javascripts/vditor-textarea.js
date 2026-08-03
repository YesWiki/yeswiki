// vditor-textarea.js -- initializes the Vditor WYSIWYG editor (ticket 16, replaces
// summernote) on every `<textarea class="vditor-html">`. The textarea itself stays the
// real, form-submitted source of truth: on every edit we write Vditor's rendered HTML
// (vditor.getHTML()) back into it and dispatch native input/change events, so every other
// script that reads/watches this field's value (validation, live conditions, etc.) keeps
// working unchanged -- only the editing surface changed, not what gets submitted or how
// it's stored server-side.
//
// The stored/submitted format is still HTML, not Markdown: on init, the textarea's existing
// HTML value is converted to Markdown via vditor.html2md() before being loaded, since
// Vditor's own internal model is Markdown-based.
import FilePickerPanel from './file-picker-panel.js'
import { legacyIconToSprite } from './yw-icon-map.js'

// One picker for the document, built on first use: its constructor binds to the single
// #YesWikiFilePickerPanel the page carries, which several editors on one form share.
let filePicker = null

// The rail lives outside the form (it contains one of its own), so it is included by the
// page template rather than by the field. A Vditor rendered somewhere that never included
// it gets its other buttons and no file button, rather than one that throws on click.
const hasFilePicker = () => document.getElementById('YesWikiFilePickerPanel') !== null

/**
 * @param getEditor a getter, not the editor: the toolbar is declared in the options
 *   object passed to `new Vditor()`, so the instance does not exist yet. The click comes
 *   much later, by which time it does -- the same reason `after`/`input` below can use
 *   it. Vditor hands the callback its own *internal* state object, which has none of the
 *   public API (insertValue included), so the argument it passes cannot be used.
 */
const filePickerMenuItem = (getEditor) => ({
  name: 'yw-file',
  tip: _t('UPLOAD_A_FILE'),
  tipPosition: 'n',
  icon: legacyIconToSprite('upload'),
  click() {
    filePicker ??= new FilePickerPanel()
    filePicker.open({
      format: 'markdown',
      onComplete: (markdown) => getEditor().insertValue(markdown)
    })
  }
})

function initVditor(textareaParam) {
  const textarea = textareaParam
  textarea.setAttribute('data-vditor-ready', '')
  textarea.style.display = 'none'

  const container = document.createElement('div')
  textarea.insertAdjacentElement('afterend', container)

  const rows = parseInt(textarea.getAttribute('rows'), 10) || 4
  const minHeight = Math.min(350, Math.max(150, rows * 30))
  const lang = textarea.getAttribute('data-vditor-lang') || 'en_US'

  // `after`/`input` fire asynchronously (Vditor loads its own parser script first), well
  // after this constructor call returns and `editor` is assigned -- safe to close over it.
  const editor = new Vditor(container, {
    cdn: 'javascripts/vendor/vditor',
    mode: 'wysiwyg',
    lang,
    minHeight,
    width: '100%',
    placeholder: textarea.getAttribute('placeholder') || '',
    toolbar: [
      'headings', 'bold', 'italic', 'strike', '|',
      'line', 'list', 'ordered-list', 'check', '|',
      'quote', 'link', 'table', '|',
      ...(hasFilePicker() ? [filePickerMenuItem(() => editor), '|'] : []),
      'emoji', '|',
      'undo', 'redo', '|',
      'code', 'inline-code'
    ],
    cache: { enable: false },
    resize: { enable: true },
    // Vditor 3.11 declares this hook optional and provides no default, but calls it
    // unconditionally while building the per-block popover -- the one holding the
    // move-up/move-down/remove buttons. The TypeError lands *between* the buttons being
    // created and setPopoverPosition() showing the panel, so the popover was built on
    // every cursor move and never displayed: the buttons existed in the DOM with
    // `display: none` and no position. A no-op is the whole fix; nothing else needs it.
    customWysiwygToolbar() {},
    // a long entry scrolls the toolbar off the top otherwise, exactly as it would the
    // ACeditor's -- which has been sticky since it was written. Vditor's own `pin` adds
    // .vditor-toolbar--pin; where it parks is styles/bazar/inputs/textarea.css.
    toolbarConfig: { pin: true },
    after() {
      editor.setValue(editor.html2md(textarea.value))
      // Vditor builds its toolbar itself, so the class that makes a button look like every
      // other button in this wiki can only be put on afterwards. Only the top-level items:
      // the entries inside a dropdown panel are menu rows, not buttons.
      container
        .querySelectorAll('.vditor-toolbar > .vditor-toolbar__item > button')
        .forEach((button) => button.classList.add('yw-btn'))
    },
    input() {
      textarea.value = editor.getHTML()
      textarea.dispatchEvent(new Event('input', { bubbles: true }))
      textarea.dispatchEvent(new Event('change', { bubbles: true }))
    }
  })
}

function scan(root) {
  root.querySelectorAll('textarea.vditor-html:not([data-vditor-ready])').forEach(initVditor)
}

// Ticket 14: one convention. This file used to carry its own MutationObserver -- it was
// the first thing in core to need "initialise content that appears later", and solved it
// alone. ywInit is that solution generalised, so the observer (which watched every DOM
// mutation in the document to catch a handful of insertions) is gone.
ywInit((root) => {
  if (root.matches && root.matches('textarea.vditor-html:not([data-vditor-ready])')) {
    initVditor(root)
  }
  scan(root.querySelectorAll ? root : document)
})
