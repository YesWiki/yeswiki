document.addEventListener('DOMContentLoaded', () => {
  const pdfContainers = document.querySelectorAll('.pdf-wrapper')
  const isPdfSupported = navigator.pdfViewerEnabled
  pdfContainers.forEach((container) => {
    const encodedUrl = container.getAttribute('data-pdf')

    if (!encodedUrl) return
    const pdfUrl = decodeURIComponent(encodedUrl)

    if (isPdfSupported) {
      // Create the object element
      const obj = document.createElement('object')
      obj.data = pdfUrl
      obj.type = 'application/pdf'
      obj.width = '100%'
      obj.height = '100%'

      obj.innerHTML = `
                <div class="pdf-fallback">
                    <a href="${pdfUrl}" class="btn btn-primary" download>Download PDF</a>
                </div>`

      container.appendChild(obj)
    } else {
      container.innerHTML = `
                <div class="pdf-fallback">
                    <a href="${pdfUrl}" class="btn btn-primary" download>Download PDF</a>
                </div>`
    }
  })
})
