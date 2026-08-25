/**
 * The file manager, re-bound every time htmx swaps it in.
 *
 * A `type="module"` script is evaluated once per document, and boosted navigation swaps the body
 * without making a new one -- so leaving the screen and coming back used to give an empty grid,
 * because nothing ran the second time. `ywInitEach` is the codebase's answer: it fires on load,
 * on `htmx:load` and on `yw:assets-ready`, once per element.
 */
ywInitEach('#yw-files', (root) => {
  const endpoint = root.dataset.ywFilesUrl
  const grid = root.querySelector('[data-yw-files-grid]')
  const empty = root.querySelector('[data-yw-files-empty]')
  const totalEl = root.querySelector('[data-yw-files-total]')
  const pager = root.querySelector('[data-yw-files-pager]')
  const pageEl = root.querySelector('[data-yw-files-page]')
  const search = root.querySelector('[data-yw-files-search]')
  const family = root.querySelector('[data-yw-files-family]')
  const sort = root.querySelector('[data-yw-files-sort]')
  const addButton = document.querySelector('[data-yw-files-add]')
  const input = document.querySelector('[data-yw-files-input]')
  const result = root.querySelector('[data-yw-files-result]')

  const FAMILY_ICONS = {
    image: 'photo',
    document: 'file',
    archive: 'database',
    audio: 'music',
    video: 'player-play',
    other: 'file',
  }

  let page = 1

  function esc(value) {
    const div = document.createElement('div')
    div.textContent = String(value ?? '')
    return div.innerHTML
  }

  function humanSize(bytes) {
    if (!bytes) return ''
    const units = ['B', 'kB', 'MB', 'GB']
    let value = bytes
    let unit = 0
    while (value >= 1024 && unit < units.length - 1) {
      value /= 1024
      unit += 1
    }
    return `${value < 10 && unit > 0 ? value.toFixed(1) : Math.round(value)} ${units[unit]}`
  }

  function icon(name) {
    return `<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#${name}"/></svg>`
  }

  function card(file) {
    const item = document.createElement('li')
    item.className = 'yw-file'
    item.dataset.ywFileTag = file.tag

    const preview =
      file.family === 'image'
        ? `<img src="${esc(file.downloadUrl)}" alt="" loading="lazy">`
        : `<span class="yw-file__glyph">${icon(FAMILY_ICONS[file.family] || FAMILY_ICONS.other)}</span>`

    item.innerHTML = `
      <a class="yw-file__preview" href="${esc(file.downloadUrl)}" title="${esc(file.name)}">${preview}</a>
      <div class="yw-file__meta">
        <a class="yw-file__name" href="${esc(file.downloadUrl)}">${esc(file.name)}</a>
        <span class="yw-file__facts">${esc(humanSize(file.size))} · ${esc(file.time)}</span>
        ${file.uploadedFrom ? `<span class="yw-file__from">${icon('link')} ${esc(file.uploadedFrom)}</span>` : ''}
      </div>
      <div class="yw-file__tools">
        <button type="button" class="yw-btn yw-btn--sm" data-yw-file-copy title="${esc(_t('ADMIN_FILES_COPY_URL'))}">${icon('copy')}</button>
        <button type="button" class="yw-btn yw-btn--sm yw-btn--danger" data-yw-file-delete title="${esc(_t('ADMIN_FILES_DELETE'))}">${icon('trash')}</button>
      </div>`

    item
      .querySelector('[data-yw-file-copy]')
      .addEventListener('click', () => copyUrl(file, item))
    item
      .querySelector('[data-yw-file-delete]')
      .addEventListener('click', () => remove(file))

    return item
  }

  async function copyUrl(file, item) {
    const absolute = new URL(file.downloadUrl, window.location.href).href
    try {
      await navigator.clipboard.writeText(absolute)
      item.classList.add('yw-file--copied')
      setTimeout(() => item.classList.remove('yw-file--copied'), 1200)
    } catch {
      window.prompt(_t('ADMIN_FILES_COPY_URL'), absolute)
    }
  }

  async function remove(file) {
    if (
      !window.confirm(
        _t('ADMIN_FILES_DELETE_CONFIRM').replace('{name}', file.name),
      )
    )
      return
    const response = await fetch(
      `${endpoint}/${encodeURIComponent(file.tag)}`,
      {
        method: 'DELETE',
      },
    )
    if (response.ok) load()
  }

  function query() {
    const [by, direction] = (sort.value || 'time.desc').split('.')
    const params = new URLSearchParams({
      page: String(page),
      sort: by,
      dir: direction,
    })
    if (search.value.trim()) params.set('search', search.value.trim())
    if (family.value) params.set('family', family.value)
    return params
  }

  async function load() {
    const response = await fetch(
      `${endpoint}${endpoint.includes('?') ? '&' : '?'}${query()}`,
      { headers: { Accept: 'application/json' } },
    )
    if (!response.ok) return
    const data = await response.json()

    grid.innerHTML = ''
    data.files.forEach((file) => grid.append(card(file)))

    empty.hidden = data.files.length > 0
    totalEl.textContent = _t('ADMIN_FILES_TOTAL').replace(
      '{count}',
      String(data.total),
    )
    pager.hidden = data.totalPages <= 1
    pageEl.textContent = `${data.currentPage} / ${data.totalPages}`
    root.querySelector('[data-yw-files-prev]').disabled = data.currentPage <= 1
    root.querySelector('[data-yw-files-next]').disabled =
      data.currentPage >= data.totalPages
  }

  function reload() {
    page = 1
    load()
  }

  let debounce
  search.addEventListener('input', () => {
    clearTimeout(debounce)
    debounce = setTimeout(reload, 250)
  })
  family.addEventListener('change', reload)
  sort.addEventListener('change', reload)

  root.querySelector('[data-yw-files-prev]').addEventListener('click', () => {
    page = Math.max(1, page - 1)
    load()
  })
  root.querySelector('[data-yw-files-next]').addEventListener('click', () => {
    page += 1
    load()
  })

  // One click: the button opens the file browser, and choosing is the upload.
  addButton?.addEventListener('click', () => input.click())

  input?.addEventListener('change', async () => {
    const chosen = [...(input.files || [])]
    if (chosen.length === 0) return

    addButton.disabled = true
    result.hidden = false
    result.className = 'yw-files__result'
    result.textContent = _t('ADMIN_FILES_UPLOADING')

    let failed = 0
    for (const file of chosen) {
      const body = new FormData()
      body.append('upFile', file)
      try {
        const response = await fetch(endpoint, { method: 'POST', body })
        if (!response.ok) failed += 1
      } catch {
        failed += 1
      }
    }

    input.value = ''
    addButton.disabled = false
    result.classList.add(
      failed === 0 ? 'yw-files__result--ok' : 'yw-files__result--failed',
    )
    result.textContent =
      failed === 0
        ? _t('ADMIN_FILES_UPLOADED').replace('{count}', String(chosen.length))
        : _t('ADMIN_FILES_UPLOAD_FAILED').replace('{count}', String(failed))

    reload()
  })

  load()
})
