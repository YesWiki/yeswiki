// ticket 14: one initialiser convention -- see ywInit in yeswiki-base-no-defer.js
const isPdfSupported = navigator.pdfViewerEnabled
ywInitEach('.pdf-wrapper', (container) => {
  const pdfUrl = container.getAttribute('data-pdf')

  if (!pdfUrl) return

  const fallback = document.createElement('div')
  fallback.className = 'pdf-fallback'
  const link = document.createElement('a')
  link.href = pdfUrl
  link.className = 'btn btn-primary'
  link.download = ''
  link.textContent = 'Download PDF'
  fallback.appendChild(link)

  if (isPdfSupported) {
    const obj = document.createElement('object')
    obj.data = pdfUrl
    obj.type = 'application/pdf'
    obj.width = '100%'
    obj.height = '100%'
    obj.appendChild(fallback)
    container.appendChild(obj)
  } else {
    container.appendChild(fallback)
  }
})
