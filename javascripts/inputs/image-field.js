function handleImageUrlInput(evt) {
  const target = evt.target || evt.srcElement
  const url = target.value.trim()
  const previewId = `${target.id}-preview`
  const previewEl = document.getElementById(previewId)

  if (!previewEl) return

  if (url && (url.startsWith('http://') || url.startsWith('https://'))) {
    const img = document.createElement('img')
    img.className = 'img-responsive'
    img.src = url
    img.alt = 'Preview'
    img.addEventListener('error', () => {
      img.style.display = 'none'
    })
    img.addEventListener('load', () => {
      img.style.display = 'block'
    })
    previewEl.replaceChildren(img)
  } else {
    previewEl.replaceChildren()
  }
}

const imageUrlInputs = document.getElementsByClassName('image-url-input')
for (let i = 0; i < imageUrlInputs.length; i += 1) {
  imageUrlInputs.item(i).addEventListener('input', handleImageUrlInput, false)
  imageUrlInputs.item(i).addEventListener('change', handleImageUrlInput, false)
}
