import MarkdownExtra from './markdown-extra.js'

export default class {
  onComplete
  extra

  get $modal() { return $('#YesWikiLinkModal') }
  get $inputUrl() { return this.$modal.find('input[name=url]') }
  get $inputText() { return this.$modal.find('input[name=text]') }
  get $inputTitle() { return this.$modal.find('input[name=title]') }
  get $inputTarget() { return this.$modal.find('select[name=target]') }

  TARGETS = ['newtab', 'modal']

  open(options) {
    this.extra = new MarkdownExtra(options.extra || '')
    const target = (this.extra.classes.filter((c) => this.TARGETS.includes(c)))[0]
    this.extra.classes = this.extra.classes.filter((c) => !this.TARGETS.includes(c))

    this.onComplete = options.onComplete
    this.$inputUrl.val(options.link)
    this.$inputText.val(options.text)
    this.$inputTitle.val(options.title)
    this.$inputTarget.val(target)

    this.$modal.find('.btn-insert').off('click').on('click', (e) => {
      this.onComplete(this.buildYesWikiCode(e))
    })

    this.$modal.find(`[data-show*=${options.action}]`).show()
    this.$modal.find(`[data-show]:not([data-show*=${options.action}])`).hide()

    this.$inputUrl.typeahead('destroy')
    if (options.action !== 'newpage') {
      // pageTags is defined in AceditorAction / Aceditor.twig
      this.$inputUrl.typeahead({ source: pageTags, items: 5 })
    }

    this.$modal.modal('show').on('shown.bs.modal', () => {
      this.$inputUrl.trigger('focus')
    })
  }

  get extractTargetFromExtra() {
    if (delete this.extra.modal) return 'modal'
    if (delete this.extra.newtab) return 'newtab'
    return ''
  }

  get extraToString() {
    let result = ''
    Object.entries(this.extra).forEach(([key, value]) => {
      result += `${key}=${value} `
    })
    return result.trim()
  }

  buildYesWikiCode(event) {
    let link = this.$inputUrl.val() || ''

    // Replace spaces by -
    link = link.replace(/\s+/g, '-')
    this.$inputUrl.val(link)

    // Validate page name or url
    const isUrl = /^https?:\/\//.test(link)
    // We do not allow "." on purpose, even if it's part of WN_PAGE_TAG regular expression
    // because we want inputs like "yeswiki.net" to be interpreted as URL and not page names
    const haveSpecialChars = /[{}|\.\\"'<>~:/?#[\]@!$&()*+,;=%]/.test(link)
    const validLink = link && (isUrl || !haveSpecialChars)
    if (!validLink) {
      event.stopImmediatePropagation()
      this.$modal.find('.link-error').removeClass('hidden')
      return
    }

    // Create wiki code
    const text = this.$inputText.val() || link
    let title = this.$inputTitle.val().trim()
    if (title) title = ` "${title}"`
    const target = this.$inputTarget.val()
    this.extra.classes.unshift(target)
    let extra = this.extra.stringify()
    if (extra) extra = `{${extra}}`

    return `[${text}](${link}${title})${extra}`
  }
}
