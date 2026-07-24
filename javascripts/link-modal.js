/* global pageTags */
import MarkdownExtra from './markdown-extra.js'

export default class {
  onComplete
  extra
  insertHandler
  inputHandler

  get modal() { return document.getElementById('YesWikiLinkModal') }
  get inputUrl() { return this.modal.querySelector('input[name=url]') }
  get inputText() { return this.modal.querySelector('input[name=text]') }
  get inputTitle() { return this.modal.querySelector('input[name=title]') }
  get inputTarget() { return this.modal.querySelector('select[name=target]') }
  get suggestions() { return this.modal.querySelector('[data-link-modal-suggestions]') }

  TARGETS = ['newtab', 'modal']

  open(options) {
    this.extra = new MarkdownExtra(options.extra || '')
    const target = (this.extra.classes.filter((c) => this.TARGETS.includes(c)))[0]
    this.extra.classes = this.extra.classes.filter((c) => !this.TARGETS.includes(c))

    this.onComplete = options.onComplete
    this.inputUrl.value = options.link || ''
    this.inputText.value = options.text || ''
    this.inputTitle.value = options.title || ''
    this.inputTarget.value = target || ''

    const insertBtn = this.modal.querySelector('.btn-insert')
    if (this.insertHandler) insertBtn.removeEventListener('click', this.insertHandler)
    this.insertHandler = (e) => {
      const code = this.buildYesWikiCode(e)
      if (code !== undefined) {
        this.onComplete(code)
        this.modal.classList.remove('yw-modal--open')
      }
    }
    insertBtn.addEventListener('click', this.insertHandler)

    this.modal.querySelectorAll('[data-show]').forEach((shownFor) => {
      const field = shownFor
      const shown = field.getAttribute('data-show').split(',').includes(options.action)
      field.style.display = shown ? '' : 'none'
    })

    this.hideSuggestions()
    if (this.inputHandler) this.inputUrl.removeEventListener('input', this.inputHandler)
    if (options.action === 'newpage') {
      // If page already exists display error message
      this.inputHandler = () => {
        const value = this.inputUrl.value.replace(/\s+/g, '-')
        this.inputUrl.value = value
        const alreadyExists = pageTags.includes(value)
        this.inputUrl.classList.toggle('error', alreadyExists)
        const alert = document.getElementById('page-already-exist-alert')
        if (alert) alert.style.display = alreadyExists ? '' : 'none'
      }
    } else {
      // pageTags is defined in AceditorAction / aceditor.twig
      this.inputHandler = () => this.updateSuggestions()
    }
    this.inputUrl.addEventListener('input', this.inputHandler)

    this.modal.classList.add('yw-modal--open')
    // wait a frame so the modal is actually visible before focusing
    requestAnimationFrame(() => this.inputUrl.focus())
  }

  updateSuggestions() {
    const value = this.inputUrl.value.trim().toLowerCase()
    const list = this.suggestions
    if (!list) return
    list.innerHTML = ''
    if (!value) {
      list.hidden = true
      return
    }
    const matches = pageTags.filter((tag) => tag.toLowerCase().includes(value)).slice(0, 5)
    if (!matches.length) {
      list.hidden = true
      return
    }
    matches.forEach((tag) => {
      const item = document.createElement('li')
      const button = document.createElement('button')
      button.type = 'button'
      button.textContent = tag
      button.addEventListener('click', () => {
        this.inputUrl.value = tag
        this.hideSuggestions()
      })
      item.appendChild(button)
      list.appendChild(item)
    })
    list.hidden = false
  }

  hideSuggestions() {
    const list = this.suggestions
    if (list) {
      list.hidden = true
      list.innerHTML = ''
    }
  }

  buildYesWikiCode(event) {
    let link = this.inputUrl.value || ''

    // Replace spaces by -
    link = link.replace(/\s+/g, '-')
    this.inputUrl.value = link

    // Validate page name or url
    const isUrl = /^https?:\/\//.test(link)
    // We do not allow "." on purpose, even if it's part of WN_PAGE_TAG regular expression
    // because we want inputs like "yeswiki.net" to be interpreted as URL and not page names
    const haveSpecialChars = /[{}|.\\"'<>~:/?#[\]@!$&()*+,;=%]/.test(link)
    const validLink = link && (isUrl || !haveSpecialChars)
    if (!validLink) {
      event.stopImmediatePropagation()
      this.modal.querySelector('.link-error').classList.remove('hidden')
      return undefined
    }

    // Create wiki code
    const text = this.inputText.value || link
    let title = this.inputTitle.value.trim()
    if (title) title = ` "${title}"`
    const target = this.inputTarget.value
    this.extra.classes.unshift(target)
    let extra = this.extra.stringify()
    if (extra) extra = `{${extra}}`

    return `[${text}](${link}${title})${extra}`
  }
}
