// javascripts/file-picker-modal.js -- ticket 17: replaces
// tools/attach/presentation/javascripts/file-upload-modal.js (jQuery + qq.js
// FileUploader). Files are their own tagged Content entries now (FileManager), so this
// modal has two panes: pick one of the files the requester can already read (GET
// /api/files) or upload a new one (POST /api/files) -- both feed the same
// configuration form before calling onComplete with a {{attach file="tag" ...}} string.
// Mirrors link-modal.js's open()/onComplete shape.
//
// The whole readable list is fetched once per opening and narrowed here rather than
// re-queried per keystroke: that is what lets each family button say how many files it
// holds and the extension list name only extensions that exist. See
// FileApiController::getFiles() for where that stops being the right trade.
import { legacyIconToSprite } from './yw-icon-map.js'

/** Sprite icon standing in for a file that has no thumbnail of its own. */
const FAMILY_ICONS = {
  image: 'photo',
  video: 'player-play',
  audio: 'music',
  document: 'file',
  other: 'paperclip'
}

/**
 * The families FileManager::FAMILIES sorts files into, in the order they are offered.
 * The labels are spelled out as literal `_t()` calls rather than looked up from a map
 * of key names, because `src/build-js-lang-keys.php` finds the keys the browser catalog
 * has to carry by scanning the scripts for exactly that literal shape.
 */
const FAMILIES = ['image', 'video', 'audio', 'document', 'other']

const familyLabel = (family) => ({
  image: () => _t('ATTACH_FILE_PICKER_FAMILY_IMAGE'),
  video: () => _t('ATTACH_FILE_PICKER_FAMILY_VIDEO'),
  audio: () => _t('ATTACH_FILE_PICKER_FAMILY_AUDIO'),
  document: () => _t('ATTACH_FILE_PICKER_FAMILY_DOCUMENT'),
  other: () => _t('ATTACH_FILE_PICKER_FAMILY_OTHER')
}[family] ?? (() => _t('ATTACH_FILE_PICKER_FAMILY_OTHER')))()

const readableSize = (bytes) => {
  const size = Number(bytes) || 0
  const units = ['B', 'kB', 'MB', 'GB']
  let unit = 0
  let value = size
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit += 1
  }
  return `${unit === 0 ? value : value.toFixed(1)} ${units[unit]}`
}

export default class {
  onComplete
  selectedTag
  selectedEntry
  insertHandler
  loadToken = 0
  files = []
  family = ''

  get modal() { return document.getElementById('YesWikiFilePickerModal') }
  get tabExisting() { return this.modal.querySelector('[data-yw-file-picker-tab="existing"]') }
  get tabUpload() { return this.modal.querySelector('[data-yw-file-picker-tab="upload"]') }
  get paneExisting() { return this.modal.querySelector('[data-yw-file-picker-pane="existing"]') }
  get paneUpload() { return this.modal.querySelector('[data-yw-file-picker-pane="upload"]') }
  get searchInput() { return this.modal.querySelector('input[name="search"]') }
  get extensionSelect() { return this.modal.querySelector('[data-yw-file-picker-extensions]') }
  get families() { return this.modal.querySelector('[data-yw-file-picker-families]') }
  get results() { return this.modal.querySelector('[data-yw-file-picker-results]') }
  get emptyMessage() { return this.modal.querySelector('[data-yw-file-picker-empty]') }
  get uploadInput() { return this.modal.querySelector('input[name="upFile"]') }
  get uploadError() { return this.modal.querySelector('.file-picker-upload-error') }
  get selectedBox() { return this.modal.querySelector('.file-picker-selected') }
  get selectedName() { return this.modal.querySelector('[data-yw-file-picker-selected-name]') }
  get optionsForm() { return this.modal.querySelector('.file-picker-options') }
  get insertBtn() { return this.modal.querySelector('.btn-insert-upload') }
  get imageOptions() { return this.modal.querySelectorAll('.image-option') }
  get pdfOptions() { return this.modal.querySelectorAll('.pdf-option') }
  get fileOptions() { return this.modal.querySelectorAll('.file-option') }

  constructor() {
    this.tabExisting.addEventListener('click', () => this.showTab('existing'))
    this.tabUpload.addEventListener('click', () => this.showTab('upload'))
    this.searchInput.addEventListener('input', () => this.applyFilters())
    this.extensionSelect.addEventListener('change', () => this.applyFilters())
    this.modal.querySelector('.btn-do-upload').addEventListener('click', () => this.uploadNewFile())
  }

  open(options) {
    this.onComplete = options.onComplete
    this.selectedTag = null
    this.selectedEntry = null
    this.optionsForm.hidden = true
    this.selectedBox.hidden = true
    this.uploadError.hidden = true
    this.uploadInput.value = ''
    this.insertBtn.disabled = true
    this.optionsForm.reset()
    this.searchInput.value = ''
    this.extensionSelect.value = ''
    this.family = ''
    this.files = []
    this.applyFilters()
    this.showTab('existing')
    this.loadFiles()

    if (this.insertHandler) this.insertBtn.removeEventListener('click', this.insertHandler)
    this.insertHandler = () => {
      if (!this.selectedTag) return
      this.onComplete(this.buildYesWikiCode())
      this.modal.classList.remove('yw-modal--open')
    }
    this.insertBtn.addEventListener('click', this.insertHandler)

    this.modal.classList.add('yw-modal--open')
    requestAnimationFrame(() => this.searchInput.focus())
  }

  showTab(tab) {
    this.tabExisting.classList.toggle('yw-btn--primary', tab === 'existing')
    this.tabUpload.classList.toggle('yw-btn--primary', tab === 'upload')
    this.paneExisting.hidden = tab !== 'existing'
    this.paneUpload.hidden = tab !== 'upload'
  }

  async loadFiles() {
    this.loadToken += 1
    const token = this.loadToken
    // no leading slash: wiki.baseUrl already ends in `?`, and `/?/api/files` is a
    // redirect to the home page, not the listing (which is why this list was empty)
    let entries = []
    try {
      const response = await fetch(wiki.url('api/files'))
      entries = response.ok ? await response.json() : []
    } catch {
      entries = []
    }
    if (token !== this.loadToken) return
    this.files = Array.isArray(entries) ? entries : []
    this.applyFilters()
  }

  /** Typing "rapport" finds a filename, typing "pdf" finds a kind of file. */
  matchesSearch(entry) {
    const search = this.searchInput.value.trim().toLowerCase()
    if (!search) return true
    return String(entry.original_filename || '').toLowerCase().includes(search)
      || String(entry.extension || '').includes(search)
  }

  /**
   * Each filter is offered from what the ones before it left, so no choice on screen
   * can lead to an empty list: the families count within the search, the extensions
   * exist within search + family.
   */
  applyFilters() {
    const withinSearch = this.files.filter((entry) => this.matchesSearch(entry))
    this.renderFamilies(withinSearch)

    const withinFamily = withinSearch.filter((entry) => !this.family || entry.family === this.family)
    this.renderExtensions(withinFamily)

    const extension = this.extensionSelect.value
    this.renderResults(
      extension ? withinFamily.filter((entry) => entry.extension === extension) : withinFamily
    )
  }

  /** One button per family that actually holds a file, each saying how many. */
  renderFamilies(withinSearch) {
    const counts = {}
    withinSearch.forEach((entry) => {
      const family = entry.family || 'other'
      counts[family] = (counts[family] || 0) + 1
    })
    // a family the search left empty stops being offered, but the one currently picked
    // stays -- a button that vanishes under the cursor that just pressed it is worse
    // than one that says 0
    const offered = FAMILIES.filter((family) => counts[family] || family === this.family)

    this.families.innerHTML = ''
    if (!offered.length) return

    this.families.appendChild(this.familyButton('', _t('ATTACH_FILE_PICKER_FAMILY_ALL'), withinSearch.length))
    offered.forEach((family) => {
      this.families.appendChild(this.familyButton(family, familyLabel(family), counts[family] || 0))
    })
  }

  familyButton(family, label, count) {
    const button = document.createElement('button')
    button.type = 'button'
    button.className = 'yw-btn file-picker__family'
    button.classList.toggle('yw-btn--primary', this.family === family)
    button.textContent = `${label} (${count})`
    button.addEventListener('click', () => {
      this.family = family
      this.applyFilters()
    })
    return button
  }

  renderExtensions(candidates) {
    const previous = this.extensionSelect.value
    const extensions = [...new Set(candidates.map((entry) => entry.extension).filter(Boolean))].sort()
    this.extensionSelect.innerHTML = ''
    const all = document.createElement('option')
    all.value = ''
    all.textContent = _t('ATTACH_FILE_PICKER_ALL_EXTENSIONS')
    this.extensionSelect.appendChild(all)
    extensions.forEach((extension) => {
      const option = document.createElement('option')
      option.value = extension
      option.textContent = `.${extension}`
      this.extensionSelect.appendChild(option)
    })
    // narrowing the family can retire the extension that was picked; falling back to
    // "all extensions" is the only honest answer, since the old one now matches nothing
    this.extensionSelect.value = extensions.includes(previous) ? previous : ''
  }

  renderResults(entries) {
    const list = this.results
    list.innerHTML = ''
    entries.forEach((entry) => {
      const item = document.createElement('li')
      item.appendChild(this.resultButton(entry))
      list.appendChild(item)
    })
    this.emptyMessage.hidden = entries.length > 0 || this.files.length === 0
  }

  resultButton(entry) {
    const button = document.createElement('button')
    button.type = 'button'
    button.className = 'file-picker__result'
    button.title = entry.original_filename
    button.classList.toggle('file-picker__result--selected', entry.tag === this.selectedTag)
    button.addEventListener('click', () => this.selectEntry(entry))

    const thumbnail = document.createElement('span')
    thumbnail.className = 'file-picker__thumb'
    if (entry.family === 'image') {
      const image = document.createElement('img')
      // the bytes live under private/, so the ACL-checked download route is the only way
      // to see them; full-size, as {{attach}} itself serves a tagged file (AttachAction)
      image.src = wiki.url(`api/files/${encodeURIComponent(entry.tag)}/download`)
      image.alt = ''
      image.loading = 'lazy'
      thumbnail.appendChild(image)
    } else {
      thumbnail.innerHTML = legacyIconToSprite(FAMILY_ICONS[entry.family] || FAMILY_ICONS.other) || ''
    }
    button.appendChild(thumbnail)

    const name = document.createElement('span')
    name.className = 'file-picker__name'
    name.textContent = entry.original_filename
    const details = document.createElement('span')
    details.className = 'file-picker__details'
    details.textContent = [entry.extension ? `.${entry.extension}` : '', readableSize(entry.size)].filter(Boolean).join(' · ')

    const meta = document.createElement('span')
    meta.className = 'file-picker__meta'
    meta.append(name, details)
    button.appendChild(meta)

    return button
  }

  async uploadNewFile() {
    const file = this.uploadInput.files[0]
    this.uploadError.hidden = true
    if (!file) return

    const formData = new FormData()
    formData.append('upFile', file)
    formData.append('pageTag', wiki.pageTag)

    let response
    let body
    try {
      response = await fetch(wiki.url('api/files'), { method: 'POST', body: formData })
      body = await response.json()
    } catch {
      response = null
    }

    if (!response || !response.ok) {
      this.uploadError.textContent = (body && body.error) || _t('ERROR_NO_FILE_UPLOADED')
      this.uploadError.hidden = false
      return
    }

    this.uploadInput.value = ''
    this.selectEntry(body)
    this.showTab('existing')
    // the new file has to reach this.files before it can be found in the list, and it
    // is what the picker now points at: clear the filters that would hide it
    this.searchInput.value = ''
    this.extensionSelect.value = ''
    this.family = ''
    this.loadFiles()
  }

  selectEntry(entry) {
    this.selectedTag = entry.tag
    this.selectedEntry = entry
    this.selectedName.textContent = entry.original_filename
    this.selectedBox.hidden = false
    this.optionsForm.hidden = false
    this.insertBtn.disabled = false
    this.configureOptionsFor(entry)
    // re-render from the already-fetched list so the newly picked row gets highlighted
    this.applyFilters()
  }

  configureOptionsFor(entry) {
    // an upload the MIME sniffer could not name is still an image if it is called .png,
    // and the alignment/size options are what makes the picker useful for one
    const isImage = entry.family === 'image'
    const isPdf = entry.extension === 'pdf' || entry.mime_type === 'application/pdf'
    this.imageOptions.forEach((shownFor) => {
      const field = shownFor
      field.style.display = isImage ? '' : 'none'
    })
    this.pdfOptions.forEach((shownFor) => {
      const field = shownFor
      field.style.display = isPdf ? '' : 'none'
    })
    this.fileOptions.forEach((shownFor) => {
      const field = shownFor
      field.style.display = isImage ? 'none' : ''
    })
    if (!isImage && !isPdf) {
      const linkText = this.optionsForm.querySelector('[name="attach_link_text"]')
      if (linkText && !linkText.value) linkText.value = this.selectedEntry.original_filename
    }
  }

  buildYesWikiCode() {
    const form = this.optionsForm
    const val = (name) => {
      const checked = form.querySelector(`[name="${name}"]:checked`)
      const field = checked || form.querySelector(`[name="${name}"]`)
      return (field && field.value) || ''
    }
    // a value containing a literal `"` would otherwise close the wikitext attribute
    // early and corrupt the emitted {{attach ...}} action
    const esc = (s) => String(s).replace(/"/g, '&quot;')

    let desc = form.querySelector('[name="attach_alt"]').value || val('attach_link_text')
    if (!desc) desc = this.selectedEntry.original_filename

    let result = `{{attach file="${esc(this.selectedTag)}" desc="${esc(desc)}"`

    if (val('attach_action_display_pdf') === '1') {
      result += ' displaypdf="1"'
    }

    const imagesize = val('attach_imagesize')
    if (imagesize) {
      result += ` size="${esc(imagesize)}"`
    }

    const align = val('attach_align')
    const checkedEffects = form.querySelectorAll('[name="attach_css_class"]:checked')
    const effects = Array.from(checkedEffects).map((el) => el.value)
    if (align || effects.length) {
      result += ` class="${esc([align, ...effects].filter(Boolean).join(' '))}"`
    }

    const link = form.querySelector('[name="attach_link"]').value
    if (link) {
      result += ` link="${esc(link)}"`
    }

    const caption = form.querySelector('[name="attach_caption"]').value
    if (caption) {
      result += ` caption="${esc(caption)}"`
    }

    if (val('attach_nofullimagelink') === '1') {
      result += ' nofullimagelink="1"'
    }

    result += '}}'
    return result
  }
}
