import { filePickerMenuItem, hasFilePicker } from './vditor-toolbar-file.js'
import { followScheme, vditorThemeOptions } from './editor-scheme.js'

const VDITOR_CDN = 'javascripts/vendor/vditor'

function initVditor(textareaParam) {
  const textarea = textareaParam
  textarea.setAttribute('data-vditor-ready', '')
  textarea.style.display = 'none'

  const container = document.createElement('div')
  textarea.insertAdjacentElement('afterend', container)

  const rows = parseInt(textarea.getAttribute('rows'), 10) || 4
  const minHeight = Math.min(350, Math.max(150, rows * 30))
  const lang = textarea.getAttribute('data-vditor-lang') || 'en_US'

  const editor = new Vditor(container, {
    cdn: VDITOR_CDN,
    ...vditorThemeOptions(VDITOR_CDN),
    mode: 'wysiwyg',
    lang,
    minHeight,
    width: '100%',
    placeholder: textarea.getAttribute('placeholder') || '',
    toolbar: [
      'headings',
      'bold',
      'italic',
      'strike',
      '|',
      'line',
      'list',
      'ordered-list',
      'check',
      '|',
      'quote',
      'link',
      'table',
      '|',
      ...(hasFilePicker()
        ? [
            filePickerMenuItem({
              onComplete: (markdown) => editor.insertValue(markdown),
            }),
            '|',
          ]
        : []),
      'emoji',
      '|',
      'undo',
      'redo',
      '|',
      'code',
      'inline-code',
    ],
    cache: { enable: false },
    resize: { enable: true },
    customWysiwygToolbar() {},
    toolbarConfig: { pin: true },
    after() {
      editor.setValue(editor.html2md(textarea.value))
      container
        .querySelectorAll('.vditor-toolbar > .vditor-toolbar__item > button')
        .forEach((button) => button.classList.add('yw-btn'))
    },
    input() {
      textarea.value = editor.getHTML()
      textarea.dispatchEvent(new Event('input', { bubbles: true }))
      textarea.dispatchEvent(new Event('change', { bubbles: true }))
    },
  })

  followScheme(editor, VDITOR_CDN)
}

function scan(root) {
  root
    .querySelectorAll('textarea.vditor-html:not([data-vditor-ready])')
    .forEach(initVditor)
}

ywInit((root) => {
  if (
    root.matches &&
    root.matches('textarea.vditor-html:not([data-vditor-ready])')
  ) {
    initVditor(root)
  }
  scan(root.querySelectorAll ? root : document)
})
