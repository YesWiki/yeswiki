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
(function() {
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
        'list', 'ordered-list', 'check', '|',
        'quote', 'link', 'table', '|',
        'emoji', '|',
        'undo', 'redo', '|',
        'fullscreen'
      ],
      cache: { enable: false },
      resize: { enable: true },
      after() {
        editor.setValue(editor.html2md(textarea.value))
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

  document.addEventListener('DOMContentLoaded', () => scan(document))

  new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node.nodeType !== 1) return
        if (node.matches && node.matches('textarea.vditor-html')) {
          initVditor(node)
        } else if (node.querySelectorAll) {
          scan(node)
        }
      })
    })
  }).observe(document.body, { childList: true, subtree: true })
}())
