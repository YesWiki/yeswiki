import AceWrapper from './ace-wrapper.js'
import {
  fillFieldMenu,
  listInsertions,
  twigInsertions,
} from './field-picker.js'

/** The field picker of the template being edited, when it has a form behind it. */
function initFieldPicker(editor) {
  const picker = document.querySelector('[data-yw-template-fields]')
  if (!picker) return
  const { kind, forms } = JSON.parse(picker.dataset.ywTemplateFields)
  const select = picker.querySelector('select')
  const menu = picker.querySelector('[data-yw-fb-template-fields]')
  const insert = ({ text }) => {
    editor.ace.insert(text)
    editor.ace.focus()
  }

  picker
    .querySelector('[data-yw-fb-template-fields-toggle]')
    .addEventListener('click', () => {
      const form = forms.find(({ id }) => id === select?.value) || forms[0]
      fillFieldMenu(
        menu,
        form.fields,
        kind === 'list' ? listInsertions : twigInsertions,
        insert,
      )
    })
}

ywInitEach('#yw-template-source', (textarea) => {
  if (textarea.readOnly) return

  const host = document.createElement('div')
  host.className = 'yw-templates__ace'
  textarea.parentNode.insertBefore(host, textarea)
  textarea.hidden = true

  const editor = new AceWrapper(host, { mode: 'ace/mode/twig', rows: 28 })
  editor.setValue(textarea.value)
  editor.ace.clearSelection()
  editor.ace.moveCursorTo(0, 0)

  editor.disableAutocompletion()

  editor.on('change', () => {
    textarea.value = editor.getValue()
  })
  textarea.form?.addEventListener('submit', () => {
    textarea.value = editor.getValue()
  })

  initFieldPicker(editor)
})
