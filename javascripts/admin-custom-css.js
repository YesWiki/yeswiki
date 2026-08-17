import AceWrapper from './ace-wrapper.js'

ywInitEach('#custom_css', (textarea) => {
  if (textarea.readOnly) return

  const host = document.createElement('div')
  host.className = 'yw-custom-css__ace'
  textarea.parentNode.insertBefore(host, textarea)
  textarea.hidden = true

  const editor = new AceWrapper(host, { mode: 'ace/mode/css', rows: 24 })
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
})
