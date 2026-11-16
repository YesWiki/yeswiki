// Sends a form in the background with XMLHttpRequest, which, unlike fetch, says how much of the body has gone.

const UNITS = ['B', 'kB', 'MB', 'GB']

/** A byte count as people read it: 840 kB, 12.4 MB. */
export function readableSize(bytes) {
  let value = Number(bytes) || 0
  let unit = 0
  while (value >= 1024 && unit < UNITS.length - 1) {
    value /= 1024
    unit += 1
  }
  return `${unit === 0 ? value : value.toFixed(1)} ${UNITS[unit]}`
}

/** The block an upload reports into: a label read out when it changes, a native bar, and how much has gone. */
export function progressBlock(label = '') {
  const block = document.createElement('div')
  block.className = 'yw-upload-progress'
  const text = document.createElement('span')
  text.className = 'yw-upload-progress__label'
  text.setAttribute('aria-live', 'polite')
  text.textContent = label
  const bar = document.createElement('progress')
  bar.className = 'yw-upload-progress__bar'
  bar.max = 1
  bar.value = 0
  const amount = document.createElement('span')
  amount.className = 'yw-upload-progress__amount'
  block.append(text, bar, amount)
  return block
}

/** Writes how much has gone into a block from progressBlock(). */
export function showProgress(block, loaded, total) {
  if (!block) return
  const bar = block.querySelector('.yw-upload-progress__bar')
  const amount = block.querySelector('.yw-upload-progress__amount')
  if (total > 0) {
    bar.max = total
    bar.value = loaded
  } else {
    bar.removeAttribute('value')
  }
  amount.textContent =
    total > 0
      ? _t('UPLOAD_PROGRESS', {
          done: readableSize(loaded),
          total: readableSize(total),
        })
      : readableSize(loaded)
}

/** POSTs formData to url; resolves { ok, status, body, text, url } as the fetch call sites read a response, rejects on a network failure or an abort. */
export function upload(
  url,
  formData,
  { progress = null, signal = null, accept = 'application/json' } = {},
) {
  return new Promise((resolve, reject) => {
    if (signal?.aborted) {
      reject(new DOMException('Aborted', 'AbortError'))
      return
    }
    const xhr = new XMLHttpRequest()
    xhr.open('POST', url)
    if (accept) xhr.setRequestHeader('Accept', accept)
    xhr.upload.addEventListener('progress', (event) =>
      showProgress(
        progress,
        event.loaded,
        event.lengthComputable ? event.total : 0,
      ),
    )
    xhr.addEventListener('load', () => {
      let body
      try {
        body = JSON.parse(xhr.responseText)
      } catch {
        body = null
      }
      resolve({
        ok: xhr.status >= 200 && xhr.status < 300,
        status: xhr.status,
        body,
        text: xhr.responseText,
        url: xhr.responseURL,
      })
    })
    xhr.addEventListener('error', () =>
      reject(new TypeError('the upload did not reach the wiki')),
    )
    xhr.addEventListener('abort', () =>
      reject(new DOMException('Aborted', 'AbortError')),
    )
    signal?.addEventListener('abort', () => xhr.abort(), { once: true })
    xhr.send(formData)
  })
}
