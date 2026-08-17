import AceWrapper from './ace-wrapper.js'

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
})
