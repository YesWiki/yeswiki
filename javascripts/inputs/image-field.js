// The image field has no file input of its own any more: it attaches a picture through
// the file-picker rail (javascripts/inputs/file-picker-field.js), which shows the chosen
// file and writes its address into the URL box below. What is left here is the preview of
// that box -- which is what the picker fills, so it previews a picked file too.

// Handle image URL preview
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
    img.addEventListener('error', () => { img.style.display = 'none' })
    img.addEventListener('load', () => { img.style.display = 'block' })
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
