function getOrientation(file, callback) {
  const reader = new FileReader()
  reader.onload = function (e) {
    const view = new DataView(e.target.result)
    if (view.getUint16(0, false) != 0xffd8) return callback(-2)
    const length = view.byteLength
    let offset = 2
    while (offset < length) {
      const marker = view.getUint16(offset, false)
      offset += 2
      if (marker == 0xffe1) {
        if (view.getUint32((offset += 2), false) != 0x45786966)
          return callback(-1)
        const little = view.getUint16((offset += 6), false) == 0x4949
        offset += view.getUint32(offset + 4, little)
        const tags = view.getUint16(offset, little)
        offset += 2
        for (let i = 0; i < tags; i++)
          if (view.getUint16(offset + i * 12, little) == 0x0112)
            return callback(view.getUint16(offset + i * 12 + 8, little))
      } else if ((marker & 0xff00) != 0xff00) break
      else offset += view.getUint16(offset, false)
    }
    return callback(-1)
  }
  reader.readAsArrayBuffer(file.slice(0, 64 * 1024))
}

const storedPreviews = new Map()

function cancelButtonFor(id) {
  return document.querySelector(`.yw-image-cancel[data-image-field="${id}"]`)
}

// Remembers the preview shown before any file was picked
function storePreview(id) {
  if (!storedPreviews.has(id)) {
    const outputEl = document.getElementById(`img-${id}`)
    storedPreviews.set(id, outputEl ? outputEl.innerHTML : '')
  }
}

function restorePreview(id) {
  const input = document.getElementById(id)
  if (input) {
    input.value = ''
  }
  const outputEl = document.getElementById(`img-${id}`)
  if (outputEl) {
    outputEl.innerHTML = storedPreviews.get(id) || ''
    $(outputEl).trigger('change')
  }
  const cancelButton = cancelButtonFor(id)
  if (cancelButton) {
    cancelButton.classList.add('hide')
  }
}

function handleFileSelect(evt) {
  const target = evt.target || evt.srcElement
  const { id } = target
  const { files } = target

  storePreview(id)
  if (files.length === 0) {
    restorePreview(id)
    return
  }

  for (var i = 0, f; (f = files[i]); i++) {
    if (!f.type.match('image.*')) {
      continue
    }
    const imageMaxSize = document.getElementById(id).dataset.maxSize
    if (f.size > imageMaxSize) {
      alert(_t('IMAGEFIELD_TOO_LARGE_IMAGE', { imageMaxSize }))
      document.getElementById(id).type = ''
      document.getElementById(id).type = 'file'
      restorePreview(id)
      continue
    }
    const reader = new FileReader()
    reader.onload = (function (theFile) {
      return function (e) {
        getOrientation(theFile, (orientation) => {
          let css = ''
          if (orientation === 6) {
            css = 'transform:rotate(90deg);'
          } else if (orientation === 8) {
            css = 'transform:rotate(270deg);'
          } else if (orientation === 3) {
            css = 'transform:rotate(180deg);'
          } else {
            css = ''
          }
          // TODO: rotate image
          css = ''
          const span = document.createElement('span')
          span.innerHTML = `<img 
            class="img-responsive"
            style="${css}"
            src="${e.target.result}"
            title="${escape(theFile.name)}"
          />`
          const outputEl = document.getElementById(`img-${id}`)
          outputEl.innerHTML = span.innerHTML
          $(outputEl).trigger('change')
          const cancelButton = cancelButtonFor(id)
          if (cancelButton) {
            cancelButton.classList.remove('hide')
          }
        })
      }
    })(f)

    reader.readAsDataURL(f)
  }
}

const imageinputs = document.getElementsByClassName('yw-image-upload')
for (let i = 0; i < imageinputs.length; i += 1) {
  imageinputs.item(i).addEventListener('change', handleFileSelect, false)
}

for (const cancelButton of document.getElementsByClassName('yw-image-cancel')) {
  cancelButton.addEventListener('click', function () {
    restorePreview(this.dataset.imageField)
  })
}

// Previews the image the URL tab points at
function handleImageUrlInput(evt) {
  const target = evt.target || evt.srcElement
  const url = target.value.trim()
  const previewId = target.id + '-preview'
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

// Moves the required constraint onto the visible tab's input
function handleTabSwitch(evt) {
  const tabPane = document.querySelector(evt.target.getAttribute('href'))
  if (!tabPane) return

  const tabContent = tabPane.closest('.tab-content')
  if (!tabContent) return

  const allPanes = tabContent.querySelectorAll('.tab-pane')
  allPanes.forEach((pane) => {
    const inputs = pane.querySelectorAll(
      'input[type="file"], input[type="url"]',
    )
    inputs.forEach((input) => {
      if (pane === tabPane) {
        if (input.dataset.wasRequired === 'true') {
          input.required = true
        }
      } else {
        if (input.required) {
          input.dataset.wasRequired = 'true'
        }
        input.required = false
      }
    })
  })
}

const imageUrlInputs = document.getElementsByClassName('image-url-input')
for (let i = 0; i < imageUrlInputs.length; i += 1) {
  imageUrlInputs.item(i).addEventListener('input', handleImageUrlInput, false)
  imageUrlInputs.item(i).addEventListener('change', handleImageUrlInput, false)
}

document
  .querySelectorAll('.file-url-tabs .nav-tabs a[data-toggle="tab"]')
  .forEach((tab) => {
    tab.addEventListener('shown.bs.tab', handleTabSwitch)
    tab.addEventListener('click', handleTabSwitch)
  })
