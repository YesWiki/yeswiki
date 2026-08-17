/* global pageTags */
import MarkdownExtra from './markdown-extra.js'
import { claimRailSlot, registerRail } from './editor-rails.js'

/** The link editor, docked as a rail beside the editor rather than shown as a modal over it -- the same slot and the same rules as the actions builder: it opens on the link the cursor is in, swaps to the next one, and closes when the cursor leaves for ordinary text, taking any half-typed change with it. */
export default class {
  onComplete
  extra
  insertHandler
  inputHandler
  editor
  isOpen = false
  isEditing = false
  openKey = null

  get panel() {
    return document.getElementById('YesWikiLinkPanel')
  }
  get inputUrl() {
    return this.panel.querySelector('input[name=url]')
  }
  get inputText() {
    return this.panel.querySelector('input[name=text]')
  }
  get inputTitle() {
    return this.panel.querySelector('input[name=title]')
  }
  get inputTarget() {
    return this.panel.querySelector('select[name=target]')
  }
  get suggestions() {
    return this.panel.querySelector('[data-link-panel-suggestions]')
  }

  TARGETS = ['newtab', 'modal']

  /** True while the rail is writing a link the document does not have yet. */
  get isPlacingNewLink() {
    return this.isOpen && !this.isEditing
  }

  constructor() {
    if (!this.panel) return
    registerRail(this)
    this.panel.querySelectorAll('[data-yw-link-panel-close]').forEach((btn) =>
      btn.addEventListener('click', (e) => {
        e.preventDefault()
        this.close()
      }),
    )
    this.inputUrl.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !this.suggestions?.hidden) {
        e.stopPropagation()
        this.hideSuggestions()
      }
    })
    this.inputUrl.addEventListener('blur', () => {
      setTimeout(() => this.hideSuggestions(), 150)
    })
  }

  open(editor, options) {
    if (!this.panel) return
    this.editor = editor
    this.onComplete = options.onComplete
    this.isEditing = options.action === 'edit'
    if (this.isOpen && options.key && options.key === this.openKey) return
    this.openKey = options.key || null

    this.extra = new MarkdownExtra(options.extra || '')
    const target = this.extra.classes.filter((c) => this.TARGETS.includes(c))[0]
    this.extra.classes = this.extra.classes.filter(
      (c) => !this.TARGETS.includes(c),
    )

    this.inputUrl.value = options.link || ''
    this.inputText.value = options.text || ''
    this.inputTitle.value = options.title || ''
    this.inputTarget.value = target || ''
    this.panel.querySelector('.link-error').classList.add('hidden')

    const insertBtn = this.panel.querySelector('.btn-insert')
    if (this.insertHandler)
      insertBtn.removeEventListener('click', this.insertHandler)
    this.insertHandler = (e) => {
      const code = this.buildYesWikiCode(e)
      if (code !== undefined) {
        this.onComplete(code)
        this.isEditing = true
        this.openKey = null
      }
    }
    insertBtn.addEventListener('click', this.insertHandler)

    this.panel.querySelectorAll('[data-show]').forEach((shownFor) => {
      const field = shownFor
      const shown = field
        .getAttribute('data-show')
        .split(',')
        .includes(options.action)
      field.style.display = shown ? '' : 'none'
    })

    this.hideSuggestions()
    if (this.inputHandler)
      this.inputUrl.removeEventListener('input', this.inputHandler)
    if (options.action === 'newpage') {
      this.inputHandler = () => {
        const value = this.inputUrl.value.replace(/\s+/g, '-')
        this.inputUrl.value = value
        const alreadyExists = pageTags.includes(value)
        this.inputUrl.classList.toggle('error', alreadyExists)
        const alert = document.getElementById('page-already-exist-alert')
        if (alert) alert.style.display = alreadyExists ? '' : 'none'
      }
    } else {
      this.inputHandler = () => this.updateSuggestions()
    }
    this.inputUrl.addEventListener('input', this.inputHandler)

    claimRailSlot(this)
    this.panel.hidden = false
    this.isOpen = true
    if (!this.isEditing) requestAnimationFrame(() => this.inputUrl.focus())
  }

  close() {
    if (!this.isOpen || !this.panel) return
    if (this.editor) this.editor.clearHighlight()
    this.panel.hidden = true
    this.isOpen = false
    this.openKey = null
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
    const matches = pageTags
      .filter((tag) => tag.toLowerCase().includes(value))
      .slice(0, 5)
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

    link = link.replace(/\s+/g, '-')
    this.inputUrl.value = link

    const isUrl = /^https?:\/\//.test(link)
    const haveSpecialChars = /[{}|.\\"'<>~:/?#[\]@!$&()*+,;=%]/.test(link)
    const validLink = link && (isUrl || !haveSpecialChars)
    if (!validLink) {
      event.stopImmediatePropagation()
      this.panel.querySelector('.link-error').classList.remove('hidden')
      return undefined
    }

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
