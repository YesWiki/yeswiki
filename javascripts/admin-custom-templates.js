import AceWrapper from './ace-wrapper.js'
import {
  fillFieldMenu,
  twigInsertions,
  vueInsertions,
  withNecessaryField,
} from './field-picker.js'

/** The field picker of the template being edited, when it has a form behind it. */
function initFieldPicker(editor) {
  const picker = document.querySelector('[data-yw-template-fields]')
  if (!picker) return
  const { kind, forms } = JSON.parse(picker.dataset.ywTemplateFields)
  const select = picker.querySelector('select')
  const notice = picker.querySelector('[data-yw-template-fields-notice]')
  const menu = picker.querySelector('[data-yw-fb-template-fields]')

  const requireField = (name) => {
    const source = editor.getValue()
    const next = withNecessaryField(source, name)
    notice.hidden = next !== null
    if (next === null) {
      notice.textContent = _t('ADMIN_TEMPLATES_NECESSARY_FIELD', {
        field: name,
      })
      return
    }
    if (next === source) return
    const cursor = editor.ace.getCursorPosition()
    editor.setValue(next)
    const added = next.split('\n').length - source.split('\n').length
    editor.ace.moveCursorToPosition({
      row: cursor.row + added,
      column: cursor.column,
    })
    editor.ace.clearSelection()
  }

  const insert = ({ text, needs }) => {
    editor.ace.insert(text)
    if (needs) requireField(needs)
    editor.ace.focus()
  }

  picker
    .querySelector('[data-yw-fb-template-fields-toggle]')
    .addEventListener('click', () => {
      const form = forms.find(({ id }) => id === select?.value) || forms[0]
      fillFieldMenu(
        menu,
        form.fields,
        kind === 'list' ? vueInsertions : twigInsertions,
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
