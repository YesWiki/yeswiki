// javascripts/file-picker-modal.js -- ticket 17: replaces
// tools/attach/presentation/javascripts/file-upload-modal.js (jQuery + qq.js
// FileUploader). Files are their own tagged Content entries now (FileManager), so this
// modal has two panes: pick one of the files the requester can already read (GET
// /api/files?search=...) or upload a new one (POST /api/files) -- both feed the same
// configuration form before calling onComplete with a {{attach file="tag" ...}} string.
// Mirrors link-modal.js's open()/onComplete shape.
export default class {
  onComplete
  selectedTag
  selectedEntry
  insertHandler
  loadToken = 0

  get modal() { return document.getElementById('YesWikiFilePickerModal') }
  get tabExisting() { return this.modal.querySelector('[data-yw-file-picker-tab="existing"]') }
  get tabUpload() { return this.modal.querySelector('[data-yw-file-picker-tab="upload"]') }
  get paneExisting() { return this.modal.querySelector('[data-yw-file-picker-pane="existing"]') }
  get paneUpload() { return this.modal.querySelector('[data-yw-file-picker-pane="upload"]') }
  get searchInput() { return this.modal.querySelector('input[name="search"]') }
  get results() { return this.modal.querySelector('[data-yw-file-picker-results]') }
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
    this.searchInput.addEventListener('input', () => this.scheduleSearch())
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
    this.renderResults([])
    this.showTab('existing')
    this.loadResults('')

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

  scheduleSearch() {
    clearTimeout(this.searchTimeout)
    this.searchTimeout = setTimeout(() => this.loadResults(this.searchInput.value.trim()), 300)
  }

  async loadResults(search) {
    this.loadToken += 1
    const token = this.loadToken
    const url = wiki.url('/api/files', search ? { search } : {})
    let entries = []
    try {
      const response = await fetch(url)
      entries = response.ok ? await response.json() : []
    } catch {
      entries = []
    }
    if (token === this.loadToken) this.renderResults(entries)
  }

  renderResults(entries) {
    const list = this.results
    list.innerHTML = ''
    entries.forEach((entry) => {
      const item = document.createElement('li')
      const button = document.createElement('button')
      button.type = 'button'
      button.className = 'file-picker__result'
      button.textContent = entry.original_filename
      button.classList.toggle('file-picker__result--selected', entry.tag === this.selectedTag)
      button.addEventListener('click', () => this.selectEntry(entry))
      item.appendChild(button)
      list.appendChild(item)
    })
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
      response = await fetch(wiki.url('/api/files'), { method: 'POST', body: formData })
      body = await response.json()
    } catch {
      response = null
    }

    if (!response || !response.ok) {
      this.uploadError.textContent = (body && body.error) || _t('ERROR_NO_FILE_UPLOADED')
      this.uploadError.hidden = false
      return
    }

    this.selectEntry(body)
    this.showTab('existing')
    this.loadResults(this.searchInput.value.trim())
  }

  selectEntry(entry) {
    this.selectedTag = entry.tag
    this.selectedEntry = entry
    this.selectedName.textContent = entry.original_filename
    this.selectedBox.hidden = false
    this.optionsForm.hidden = false
    this.insertBtn.disabled = false
    this.configureOptionsFor(entry.mime_type || '')
    // re-render from the already-fetched list so the newly picked row gets highlighted
    this.loadResults(this.searchInput.value.trim())
  }

  configureOptionsFor(mimeType) {
    const isImage = mimeType.startsWith('image/')
    const isPdf = mimeType === 'application/pdf'
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

    let desc = form.querySelector('[name="attach_alt"]').value || val('attach_link_text')
    if (!desc) desc = this.selectedEntry.original_filename

    let result = `{{attach file="${this.selectedTag}" desc="${desc}"`

    if (val('attach_action_display_pdf') === '1') {
      result += ' displaypdf="1"'
    }

    const imagesize = val('attach_imagesize')
    if (imagesize) {
      result += ` size="${imagesize}"`
    }

    const align = val('attach_align')
    const checkedEffects = form.querySelectorAll('[name="attach_css_class"]:checked')
    const effects = Array.from(checkedEffects).map((el) => el.value)
    if (align || effects.length) {
      result += ` class="${[align, ...effects].filter(Boolean).join(' ')}"`
    }

    const link = form.querySelector('[name="attach_link"]').value
    if (link) {
      result += ` link="${link}"`
    }

    const caption = form.querySelector('[name="attach_caption"]').value
    if (caption) {
      result += ` caption="${caption}"`
    }

    if (val('attach_nofullimagelink') === '1') {
      result += ' nofullimagelink="1"'
    }

    result += '}}'
    return result
  }
}
