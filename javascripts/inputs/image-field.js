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

const originalPreviews = new Map()

const cancelButtonFor = (inputId) =>
  document.querySelector(`[data-yw-image-cancel="${inputId}"]`)

const currentPreviewFor = (inputId) =>
  document.getElementById(`img-${inputId.replace(/_url$/, '')}`)

/** Shows the image just chosen in place of the current one, and offers to undo the choice. */
function handleImageChoice(evt) {
  const input = evt.target
  const cancelButton = cancelButtonFor(input.id)
  if (!cancelButton) return

  const url = input.value.trim()
  const preview = currentPreviewFor(input.id)
  if (preview) {
    if (!originalPreviews.has(input.id)) {
      originalPreviews.set(input.id, [...preview.childNodes])
    }
    if (url) {
      const img = document.createElement('img')
      img.className = 'img-responsive'
      img.src = url
      img.alt = ''
      preview.replaceChildren(img)
    } else {
      preview.replaceChildren(...originalPreviews.get(input.id))
    }
  }
  cancelButton.hidden = !url
}

/** Forgets the image just chosen and puts back what the field showed before. */
function cancelImageChoice(evt) {
  const inputId = evt.currentTarget.dataset.ywImageCancel
  const input = document.getElementById(inputId)
  if (!input) return

  input.value = ''
  const chosen = document.querySelector(
    `[data-yw-file-picker-chosen="${inputId}"]`,
  )
  if (chosen) {
    chosen.textContent = ''
    chosen.hidden = true
  }
  input.dispatchEvent(new Event('change', { bubbles: true }))
}

document.querySelectorAll('[data-yw-image-cancel]').forEach((button) => {
  const input = document.getElementById(button.dataset.ywImageCancel)
  if (!input) return
  input.addEventListener('change', handleImageChoice)
  input.addEventListener('input', handleImageChoice)
  button.addEventListener('click', cancelImageChoice)
})
