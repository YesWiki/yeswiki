// javascripts/admin-custom-templates.js -- editing a template override (ticket 30).
//
// Same arrangement as the custom-CSS screen: the textarea in the template is the real field,
// ACE is layered over it and writes back into it, and a page where this module never loads
// still has a working editor. That matters more here than there -- this is the screen you
// reach for when an override has broken the wiki.
//
// `ace/mode/twig`, vendored from ace-builds alongside the HTML and CSS modes: it knows the
// whole tag set (`block`, `extends`, `embed`, `verbatim`, ...) and brings HTML, CSS and
// JavaScript rules with it, so a template highlights as markup *and* as Twig. It spawns no
// worker, which is the right answer here -- an HTML validator would report every
// `{% if %}` inside an attribute as a broken tag.
//
// ywInitEach rather than a one-shot at module level, for the same reason as the custom-CSS
// screen: an ES module is evaluated once per document, and every admin screen arrives through
// an hx-boost navigation, so a top-level setup builds the editor on the first arrival only.
import AceWrapper from './ace-wrapper.js'

ywInitEach('#yw-template-source', (textarea) => {
  if (textarea.readOnly) return

  // ACE takes its host node over completely, so it gets one of its own and the textarea
  // survives as the thing the form submits
  const host = document.createElement('div')
  host.className = 'yw-templates__ace'
  textarea.parentNode.insertBefore(host, textarea)
  textarea.hidden = true

  const editor = new AceWrapper(host, { mode: 'ace/mode/twig', rows: 28 })
  editor.setValue(textarea.value)
  // ACE selects everything it is given; a cursor at the top is what a person expects
  editor.ace.clearSelection()
  editor.ace.moveCursorTo(0, 0)

  // the wiki's action names are not what you want offered inside a Twig template
  editor.disableAutocompletion()

  // on change as well as on submit: the form can be sent by pressing Enter, and a save that
  // posted the pre-edit textarea would look exactly like a save that worked
  editor.on('change', () => {
    textarea.value = editor.getValue()
  })
  textarea.form?.addEventListener('submit', () => {
    textarea.value = editor.getValue()
  })
})
