// javascripts/admin-custom-css.js -- the wiki's own stylesheet, edited as code (ticket 30).
//
// The textarea in the template is the real field: it is what the form posts, and it is what
// the screen falls back to if this module never loads. ACE is layered over it and writes
// back into it, rather than replacing it -- an editor that failed to start would otherwise
// take the stylesheet with it.
//
// ywInitEach rather than a one-shot at module level: this screen arrives through an hx-boost
// navigation, and a module is evaluated once per *document*, not once per page. Set up at the
// top level, the editor was built on the first arrival and never again -- leave the screen and
// come back and the stylesheet showed as a bare textarea.
import AceWrapper from './ace-wrapper.js'

ywInitEach('#custom_css', (textarea) => {
  if (textarea.readOnly) return

  // ACE needs an element of its own: it takes the node over completely, and the textarea
  // has to survive as the thing the form submits
  const host = document.createElement('div')
  host.className = 'yw-custom-css__ace'
  textarea.parentNode.insertBefore(host, textarea)
  textarea.hidden = true

  const editor = new AceWrapper(host, { mode: 'ace/mode/css', rows: 24 })
  editor.setValue(textarea.value)
  // ACE selects everything it is given; a cursor at the top is what a person expects
  editor.ace.clearSelection()
  editor.ace.moveCursorTo(0, 0)

  // autocompletion here would offer the wiki's action names inside a stylesheet
  editor.disableAutocompletion()

  // On input rather than only on submit: the form can also be sent by pressing Enter in
  // some browsers, and a save that posted the pre-edit textarea would look exactly like a
  // save that worked.
  editor.on('change', () => {
    textarea.value = editor.getValue()
  })
  textarea.form?.addEventListener('submit', () => {
    textarea.value = editor.getValue()
  })
})
