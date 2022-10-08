export default class {
  onComplete

  get $modal() { return $('#YesWikiLinkModal') }
  get $inputUrl() { return this.$modal.find('input[name=url]') }
  get $inputText() { return this.$modal.find('input[name=text]') }

  open(options) {
    this.onComplete = options.onComplete
    this.$inputUrl.val(options.link)
    this.$inputText.val(options.text)

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

  buildYesWikiCode(event) {
    let wikiurl = this.$inputUrl.val() || ''

    // Replace spaces by -
    wikiurl = wikiurl.replace(/\s+/g, '-')
    this.$inputUrl.val(wikiurl)

    // Validate page name or url
    const isUrl = /^https?:\/\//.test(wikiurl)
    // We do not allow "." on purpose, even if it's part of WN_PAGE_TAG regular expression
    // because we want inputs like "yeswiki.net" to be interpreted as URL and not page names
    const haveSpecialChars = /[{}|\.\\"'<>~:/?#[\]@!$&()*+,;=%]/.test(wikiurl)
    const validWikiUrl = wikiurl && (isUrl || !haveSpecialChars)
    if (!validWikiUrl) {
      event.stopImmediatePropagation()
      this.$modal.find('.link-error').removeClass('hidden')
      return
    }

    // Create wiki code
    const text = this.$inputText.val() || wikiurl
    const linkOption = this.$modal.find('.link-options').val()
    let result = ''
    if (linkOption === 'link') {
      result = `[[${wikiurl} ${text}]]`
    } else {
      const klass = ({ ext: 'new-window', $modal: '$modalbox' })[linkOption]
      const params = klass ? `class="${klass}" ` : ''
      result = `{{button link="${wikiurl}" text="${text}" ${params}nobtn="1"}}`
    }

    return result
  }
}
