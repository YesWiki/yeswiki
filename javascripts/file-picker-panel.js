import { legacyIconToSprite } from './yw-icon-map.js'
import { claimRailSlot, registerRail } from './editor-rails.js'
import {
  attachCode,
  attachMarkdown,
  downloadUrl,
  uploadFile,
} from './upload-file.js'
import { progressBlock, readableSize } from './upload.js'

/** Sprite icon standing in for a file that has no thumbnail of its own. */
const FAMILY_ICONS = {
  image: 'photo',
  video: 'player-play',
  audio: 'music',
  document: 'file',
  other: 'paperclip',
}

/** The families FileManager::FAMILIES sorts files into, in the order they are offered. */
const FAMILIES = ['image', 'video', 'audio', 'document', 'other']

const familyLabel = (family) =>
  (
    ({
      image: () => _t('ATTACH_FILE_PICKER_FAMILY_IMAGE'),
      video: () => _t('ATTACH_FILE_PICKER_FAMILY_VIDEO'),
      audio: () => _t('ATTACH_FILE_PICKER_FAMILY_AUDIO'),
      document: () => _t('ATTACH_FILE_PICKER_FAMILY_DOCUMENT'),
      other: () => _t('ATTACH_FILE_PICKER_FAMILY_OTHER'),
    })[family] ?? (() => _t('ATTACH_FILE_PICKER_FAMILY_OTHER'))
  )()

/** What a rail pinned to one family accepts, in the two vocabularies a browser uses: the `accept` attribute of the file dialog, and the MIME type of the file that comes back. */
const FAMILY_MIME_PREFIX = {
  image: 'image/',
  video: 'video/',
  audio: 'audio/',
}

export default class {
  onComplete
  onPick
  only = ''
  pageTag = ''
  uploadToken = ''
  selectedTag
  selectedEntry
  insertHandler
  loadToken = 0
  files = []
  family = ''
  /** 'browse' | 'upload' | 'chosen' -- see showView(). */
  view = 'browse'
  uploading = null

  get panel() {
    return document.getElementById('YesWikiFilePickerPanel')
  }
  get uploadOpenBtn() {
    return this.panel.querySelector('[data-yw-file-picker-upload-open]')
  }
  get backBtn() {
    return this.panel.querySelector('[data-yw-file-picker-back]')
  }
  get paneExisting() {
    return this.panel.querySelector('[data-yw-file-picker-pane="existing"]')
  }
  get searchInput() {
    return this.panel.querySelector('input[name="search"]')
  }
  get extensionSelect() {
    return this.panel.querySelector('[data-yw-file-picker-extensions]')
  }
  get families() {
    return this.panel.querySelector('[data-yw-file-picker-families]')
  }
  get results() {
    return this.panel.querySelector('[data-yw-file-picker-results]')
  }
  get emptyMessage() {
    return this.panel.querySelector('[data-yw-file-picker-empty]')
  }
  get uploadInput() {
    return this.panel.querySelector('[data-yw-file-picker-input]')
  }
  get uploadBusy() {
    return this.panel.querySelector('.file-picker-upload-busy')
  }
  get uploadError() {
    return this.panel.querySelector('.file-picker-upload-error')
  }
  get selectedBox() {
    return this.panel.querySelector('.file-picker-selected')
  }
  get selectedPreview() {
    return this.panel.querySelector('[data-yw-file-picker-selected-preview]')
  }
  get selectedName() {
    return this.panel.querySelector('[data-yw-file-picker-selected-name]')
  }
  get optionsForm() {
    return this.panel.querySelector('.file-picker-options')
  }
  get insertBtn() {
    return this.panel.querySelector('.btn-insert-upload')
  }
  /** Every option whose visibility depends on the chosen file or the target syntax. */
  get conditionalOptions() {
    return this.panel.querySelectorAll(
      '.image-option, .pdf-option, .file-option, [data-yw-file-picker-wiki-only]',
    )
  }

  isOpen = false

  constructor() {
    if (!this.panel) return
    registerRail(this)
    this.panel.querySelectorAll('[data-yw-file-picker-close]').forEach((btn) =>
      btn.addEventListener('click', (e) => {
        e.preventDefault()
        this.close()
      }),
    )
    this.uploadOpenBtn.addEventListener(
      'click',
      () => this.isOpen && this.uploadInput.click(),
    )
    this.uploadInput.addEventListener(
      'change',
      () => this.isOpen && this.uploadNewFile(),
    )
    this.backBtn.addEventListener('click', () => this.isOpen && this.goBack())
    this.searchInput.addEventListener(
      'input',
      () => this.isOpen && this.applyFilters(),
    )
    this.extensionSelect.addEventListener(
      'change',
      () => this.isOpen && this.applyFilters(),
    )
  }

  /** Two kinds of caller, and they want different things back. */
  open(options) {
    if (!this.panel) return
    this.onComplete = options.onComplete
    this.onPick = options.onPick ?? null
    this.format = options.format === 'markdown' ? 'markdown' : 'wiki'
    this.clearSelection()
    this.uploadError.hidden = true
    this.uploadInput.value = ''
    this.searchInput.value = ''
    this.extensionSelect.value = ''
    this.only = options.only ?? ''
    this.pageTag = options.pageTag || wiki.pageTag
    this.uploadToken = options.uploadToken ?? ''
    this.family = this.only
    this.uploadInput.accept = FAMILY_MIME_PREFIX[this.only]
      ? `${FAMILY_MIME_PREFIX[this.only]}*`
      : ''
    this.files = []
    this.applyFilters()
    this.showView('browse')
    this.loadFiles()
    this.insertBtn.textContent = this.onPick
      ? _t('ATTACH_FILE_PICKER_USE_THIS_FILE')
      : _t('INSERT')

    if (this.insertHandler)
      this.insertBtn.removeEventListener('click', this.insertHandler)
    this.insertHandler = () => {
      if (!this.selectedTag) return
      if (this.onPick) {
        this.onPick({
          tag: this.selectedTag,
          url: this.downloadUrl(),
          entry: this.selectedEntry,
        })
      } else {
        this.onComplete(
          this.format === 'markdown'
            ? this.buildMarkdown()
            : this.buildYesWikiCode(),
        )
      }
      this.close()
    }
    this.insertBtn.addEventListener('click', this.insertHandler)

    if (!this.onPick) claimRailSlot(this)
    this.panel.classList.toggle('yw-rail--over', Boolean(this.onPick))
    this.panel.hidden = false
    this.isOpen = true
    requestAnimationFrame(() => this.searchInput.focus())
  }

  close() {
    if (!this.isOpen || !this.panel) return
    this.uploading?.abort()
    this.panel.hidden = true
    this.isOpen = false
  }

  /** The rail is browsing or looking at a choice, and each state owns the whole panel. */
  showView(view) {
    this.view = view
    this.paneExisting.hidden = view !== 'browse'
    this.uploadOpenBtn.hidden = view !== 'browse'
    this.backBtn.hidden = view === 'browse'
    this.selectedBox.hidden = view !== 'chosen'
    this.optionsForm.hidden = view !== 'chosen' || this.onPick !== null
  }

  /** Let the button be pressed again, whatever the upload did. */
  finishUploading() {
    this.uploading = null
    this.uploadOpenBtn.disabled = false
    this.uploadBusy.hidden = true
    this.uploadBusy.replaceChildren()
  }

  /** Back out of a choice, which unmakes it. */
  goBack() {
    if (this.view === 'chosen') {
      this.clearSelection()
    }
    this.uploadError.hidden = true
    this.showView('browse')
    this.applyFilters()
  }

  clearSelection() {
    this.selectedTag = null
    this.selectedEntry = null
    this.selectedPreview.innerHTML = ''
    this.insertBtn.disabled = true
    this.optionsForm.reset()
  }

  async loadFiles() {
    this.loadToken += 1
    const token = this.loadToken
    let entries
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
    return (
      String(entry.original_filename || '')
        .toLowerCase()
        .includes(search) || String(entry.extension || '').includes(search)
    )
  }

  /** Each filter is offered from what the ones before it left, so no choice on screen can lead to an empty list: the families count within the search, the extensions exist within search + family. */
  applyFilters() {
    const offered = this.only
      ? this.files.filter((entry) => entry.family === this.only)
      : this.files
    const withinSearch = offered.filter((entry) => this.matchesSearch(entry))
    this.renderFamilies(withinSearch)

    const withinFamily = withinSearch.filter(
      (entry) => !this.family || entry.family === this.family,
    )
    this.renderExtensions(withinFamily)

    const extension = this.extensionSelect.value
    this.renderResults(
      extension
        ? withinFamily.filter((entry) => entry.extension === extension)
        : withinFamily,
    )
  }

  /** One button per family that actually holds a file, each saying how many. */
  renderFamilies(withinSearch) {
    if (this.only) {
      this.families.innerHTML = ''
      return
    }

    const counts = {}
    withinSearch.forEach((entry) => {
      const family = entry.family || 'other'
      counts[family] = (counts[family] || 0) + 1
    })
    const offered = FAMILIES.filter(
      (family) => counts[family] || family === this.family,
    )

    this.families.innerHTML = ''
    if (!offered.length) return

    this.families.appendChild(
      this.familyButton(
        '',
        _t('ATTACH_FILE_PICKER_FAMILY_ALL'),
        withinSearch.length,
      ),
    )
    offered.forEach((family) => {
      this.families.appendChild(
        this.familyButton(family, familyLabel(family), counts[family] || 0),
      )
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
    const extensions = [
      ...new Set(candidates.map((entry) => entry.extension).filter(Boolean)),
    ].sort()
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
    button.classList.toggle(
      'file-picker__result--selected',
      entry.tag === this.selectedTag,
    )
    button.addEventListener('click', () => this.selectEntry(entry))

    const thumbnail = document.createElement('span')
    thumbnail.className = 'file-picker__thumb'
    if (entry.family === 'image') {
      const image = document.createElement('img')
      image.src = wiki.url(
        `api/files/${encodeURIComponent(entry.tag)}/download`,
      )
      image.alt = ''
      image.loading = 'lazy'
      thumbnail.appendChild(image)
    } else {
      thumbnail.innerHTML =
        legacyIconToSprite(FAMILY_ICONS[entry.family] || FAMILY_ICONS.other) ||
        ''
    }
    button.appendChild(thumbnail)

    const name = document.createElement('span')
    name.className = 'file-picker__name'
    name.textContent = entry.original_filename
    const details = document.createElement('span')
    details.className = 'file-picker__details'
    details.textContent = [
      entry.extension ? `.${entry.extension}` : '',
      readableSize(entry.size),
    ]
      .filter(Boolean)
      .join(' · ')

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

    if (this.only && !this.matchesOnlyFamily(file)) {
      this.uploadError.textContent = _t('ATTACH_FILE_PICKER_WRONG_FAMILY')
      this.uploadError.hidden = false
      this.uploadInput.value = ''
      return
    }

    this.uploadOpenBtn.disabled = true
    const progress = progressBlock(file.name)
    this.uploadBusy.replaceChildren(progress)
    this.uploadBusy.hidden = false
    const uploading = new AbortController()
    this.uploading = uploading

    let entry
    try {
      entry = await uploadFile(file, {
        pageTag: this.pageTag,
        uploadToken: this.uploadToken,
        progress,
        signal: uploading.signal,
      })
    } catch (error) {
      this.finishUploading()
      this.uploadInput.value = ''
      if (uploading.signal.aborted) return
      this.uploadError.textContent =
        error?.message || _t('ERROR_NO_FILE_UPLOADED')
      this.uploadError.hidden = false

      return
    }

    this.finishUploading()
    this.uploadInput.value = ''
    this.selectEntry(entry)
    this.searchInput.value = ''
    this.extensionSelect.value = ''
    this.family = this.only
    this.loadFiles()
  }

  selectEntry(entry) {
    this.selectedTag = entry.tag
    this.selectedEntry = entry
    this.selectedName.textContent = entry.original_filename
    this.showPreview(entry)
    this.insertBtn.disabled = false
    if (!this.onPick) this.configureOptionsFor(entry)
    this.applyFilters()
    this.showView('chosen')
  }

  /** What the chosen file looks like: a large preview for an image, its family glyph for anything else. */
  showPreview(entry) {
    const holder = this.selectedPreview
    holder.innerHTML = ''
    holder.classList.toggle(
      'file-picker-selected__preview--image',
      entry.family === 'image',
    )

    if (entry.family === 'image') {
      const image = document.createElement('img')
      image.src = wiki.url(
        `api/files/${encodeURIComponent(entry.tag)}/download`,
      )
      image.alt = ''
      holder.appendChild(image)

      return
    }

    holder.innerHTML =
      legacyIconToSprite(FAMILY_ICONS[entry.family] || FAMILY_ICONS.other) || ''
  }

  configureOptionsFor(entry) {
    const isImage = entry.family === 'image'
    const isPdf =
      entry.extension === 'pdf' || entry.mime_type === 'application/pdf'

    const hidden = (option) => {
      if (
        this.format === 'markdown' &&
        option.hasAttribute('data-yw-file-picker-wiki-only')
      )
        return true
      if (option.classList.contains('image-option')) return !isImage
      if (option.classList.contains('pdf-option')) return !isPdf
      if (option.classList.contains('file-option')) return isImage
      return false
    }
    this.conditionalOptions.forEach((element) => {
      const option = element
      option.style.display = hidden(option) ? 'none' : ''
    })

    if (!isImage && !isPdf) {
      const linkText = this.optionsForm.querySelector(
        '[name="attach_link_text"]',
      )
      if (linkText && !linkText.value)
        linkText.value = this.selectedEntry.original_filename
    }
  }

  /** The {{attach}} for the chosen file, with what the options form says. */
  buildYesWikiCode() {
    const form = this.optionsForm
    const val = (name) => {
      const checked = form.querySelector(`[name="${name}"]:checked`)
      const field = checked || form.querySelector(`[name="${name}"]`)
      return (field && field.value) || ''
    }
    return attachCode(this.selectedEntry, {
      desc:
        form.querySelector('[name="attach_alt"]').value ||
        val('attach_link_text'),
      displayPdf: val('attach_action_display_pdf') === '1',
      size: val('attach_imagesize'),
      align: val('attach_align'),
      effects: Array.from(
        form.querySelectorAll('[name="attach_css_class"]:checked'),
      ).map((element) => element.value),
      link: form.querySelector('[name="attach_link"]').value,
      caption: form.querySelector('[name="attach_caption"]').value,
      noFullImageLink: val('attach_nofullimagelink') === '1',
    })
  }

  /** Whether a file the browser is offering is of the family this rail was pinned to. */
  matchesOnlyFamily(file) {
    const prefix = FAMILY_MIME_PREFIX[this.only]

    return !prefix || String(file.type).startsWith(prefix)
  }

  /** Where the chosen file is served from -- the one address that survives a rename. */
  downloadUrl() {
    return downloadUrl(this.selectedEntry ?? { tag: this.selectedTag })
  }

  /** The markdown for the chosen file, with what the options form says. */
  buildMarkdown() {
    const form = this.optionsForm
    return attachMarkdown(this.selectedEntry, {
      alt: form.querySelector('[name="attach_alt"]').value,
      linkText: form.querySelector('[name="attach_link_text"]').value,
    })
  }
}
